<?php

/**
 * Transactional character marketplace for Zealot Market.
 *
 * Funds are kept in transferable coins. A listed character is moved to a
 * dedicated escrow account until the listing is cancelled, expires or sells.
 */
class ZealotMarket
{
    public const STATUS_ACTIVE = 0;
    public const STATUS_SOLD = 1;
    public const STATUS_CANCELLED = 2;
    public const STATUS_EXPIRED = 3;

    private $db;
    private array $config;

    public function __construct($db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public static function csrfToken(): string
    {
        $token = getSession('zealot_market_csrf');
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            setSession('zealot_market_csrf', $token);
        }

        return $token;
    }

    public function verifyCsrf(?string $token): void
    {
        if (!is_string($token) || !hash_equals(self::csrfToken(), $token)) {
            throw new RuntimeException('Your Zealot Market form has expired. Please try again.');
        }
    }

    public function isInstalled(): bool
    {
        if (!$this->db->hasTable('myaac_charbazaar')
            || !$this->db->hasTable('myaac_charbazaar_bid')
            || !$this->db->hasTable('myaac_zealot_market_ledger')
            || !$this->db->hasTable('myaac_zealot_market_hidden_history')) {
            return false;
        }

        foreach (['starting_price', 'escrow_account_id', 'listing_fee', 'tax_rate', 'character_snapshot', 'final_price', 'completed_at', 'cancelled_at'] as $column) {
            if (!$this->db->hasColumn('myaac_charbazaar', $column)) {
                return false;
            }
        }

        return $this->db->hasColumn('myaac_charbazaar_bid', 'is_winning');
    }

    /** Backfill frozen listings that predate the character snapshot feature. */
    public function backfillMissingSnapshots(): int
    {
        if (!$this->isInstalled()) {
            throw new RuntimeException('Zealot Market is not installed.');
        }

        $listingIds = $this->db->query(
            'SELECT `id` FROM `myaac_charbazaar` WHERE `character_snapshot` IS NULL OR `character_snapshot` = \'\''
        )->fetchAll();
        $updated = 0;

        foreach ($listingIds as $row) {
            $this->db->beginTransaction();
            try {
                $listing = $this->listingForUpdate((int) $row['id']);
                if (!empty($listing['character_snapshot'])) {
                    $this->db->commit();
                    continue;
                }
                $player = $this->characterForUpdate((int) $listing['player_id']);
                if (!$player) {
                    $this->db->commit();
                    continue;
                }
                $snapshot = $this->captureCharacterSnapshot($player);
                $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if (!is_string($encoded)) {
                    throw new RuntimeException('The character inventory snapshot could not be encoded.');
                }
                $this->db->exec(
                    'UPDATE `myaac_charbazaar` SET `character_snapshot` = ' . $this->db->quote($encoded)
                    . ' WHERE `id` = ' . (int) $listing['id']
                );
                $this->db->commit();
                $this->exportSnapshotOutfit($snapshot);
                $updated++;
            } catch (Throwable $exception) {
                $this->rollback();
                error_log('Zealot Market snapshot backfill failed for listing ' . (int) $row['id'] . ': ' . $exception->getMessage());
            }
        }

        return $updated;
    }

    public function createListing(int $sellerId, int $playerId, int $startingPrice, int $days): int
    {
        $listingFee = max(0, (int) ($this->config['bazaar_create'] ?? 0));
        $days = max(1, min(30, $days));

        if ($startingPrice < 1) {
            throw new RuntimeException('The starting price must be at least 1 transferable Zealot Coin.');
        }

        $this->db->beginTransaction();
        try {
            $seller = $this->accountForUpdate($sellerId);
            if (!$seller) {
                throw new RuntimeException('Your account could not be found.');
            }
            if ((int) $seller['coins_transferable'] < $listingFee) {
                throw new RuntimeException('You do not have enough transferable Zealot Coins for the listing fee.');
            }

            $player = $this->characterForUpdate($playerId);
            if (!$player || (int) $player['account_id'] !== $sellerId) {
                throw new RuntimeException('You can only list a character that belongs to your account.');
            }

            if ($this->isPlayerOnline($playerId)) {
                throw new RuntimeException('Log out this character before listing it. Zealot Market captures its outfit, equipment, backpack and depot only after the server has saved the character.');
            }

            $active = $this->db->query(
                'SELECT `id` FROM `myaac_charbazaar` WHERE `player_id` = ' . $playerId
                . ' AND `status` = ' . self::STATUS_ACTIVE . ' FOR UPDATE'
            )->fetch();
            if ($active) {
                throw new RuntimeException('This character already has an active market listing.');
            }

            $escrowId = $this->escrowAccountId();
            $snapshot = $this->captureCharacterSnapshot($player);
            $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if (!is_string($snapshotJson)) {
                throw new RuntimeException('The character inventory snapshot could not be created.');
            }
            $this->debitCoins($sellerId, $listingFee, 'listing_fee', null, 'Listing fee for ' . $player['name']);

            $this->db->exec(
                'UPDATE `players` SET `account_id` = ' . $escrowId . ' WHERE `id` = ' . $playerId
            );

            $this->db->exec(
                'INSERT INTO `myaac_charbazaar` (`account_old`, `account_new`, `player_id`, `price`, `starting_price`, `date_end`, `date_start`, `bid_account`, `bid_price`, `status`, `escrow_account_id`, `listing_fee`, `tax_rate`, `character_snapshot`) VALUES ('
                . $sellerId . ', 0, ' . $playerId . ', ' . $startingPrice . ', ' . $startingPrice . ', '
                . 'DATE_ADD(NOW(), INTERVAL ' . $days . ' DAY), NOW(), 0, 0, ' . self::STATUS_ACTIVE . ', '
                . $escrowId . ', ' . $listingFee . ', ' . $this->taxRate() . ', ' . $this->db->quote($snapshotJson) . ')'
            );
            $listingId = (int) $this->db->lastInsertId();
            $this->ledger($sellerId, -$listingFee, 'listing_fee', $listingId, 'Listing fee');
            $this->db->commit();

            $this->exportSnapshotOutfit($snapshot);

            return $listingId;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function placeBid(int $buyerId, int $listingId, int $amount): void
    {
        if ($amount < 1) {
            throw new RuntimeException('Enter a valid bid amount.');
        }

        $this->db->beginTransaction();
        try {
            $listing = $this->listingForUpdate($listingId);
            $this->assertListingIsBiddable($listing, $buyerId);

            $currentBid = max((int) $listing['starting_price'], (int) $listing['bid_price']);
            $minimumBid = $this->minimumBidFor($listing);
            if ($amount < $minimumBid) {
                throw new RuntimeException('Your bid must be at least ' . number_format($minimumBid) . ' transferable Zealot Coins.');
            }

            $previousBidder = (int) $listing['bid_account'];
            $previousBid = (int) $listing['bid_price'];
            $charge = $amount;

            if ($previousBidder === $buyerId && $previousBid > 0) {
                $charge = $amount - $previousBid;
            }

            if ($charge > 0) {
                $this->debitCoins($buyerId, $charge, 'bid_hold', $listingId, 'Bid held in escrow');
            }

            if ($previousBidder > 0 && $previousBidder !== $buyerId && $previousBid > 0) {
                $this->creditCoins($previousBidder, $previousBid, 'bid_refund', $listingId, 'Outbid refund');
            }

            $this->db->exec('UPDATE `myaac_charbazaar_bid` SET `is_winning` = 0 WHERE `auction_id` = ' . $listingId);
            $this->db->exec(
                'INSERT INTO `myaac_charbazaar_bid` (`account_id`, `auction_id`, `bid`, `is_winning`) VALUES ('
                . $buyerId . ', ' . $listingId . ', ' . $amount . ', 1)'
            );
            $this->db->exec(
                'UPDATE `myaac_charbazaar` SET `price` = ' . $amount . ', `bid_account` = ' . $buyerId
                . ', `bid_price` = ' . $amount . ' WHERE `id` = ' . $listingId
            );

            $this->ledger($buyerId, -$charge, 'bid_hold', $listingId, 'Current winning bid');
            if ($previousBidder > 0 && $previousBidder !== $buyerId && $previousBid > 0) {
                $this->ledger($previousBidder, $previousBid, 'bid_refund', $listingId, 'Outbid refund');
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function cancelListing(int $sellerId, int $listingId): void
    {
        $this->db->beginTransaction();
        try {
            $listing = $this->listingForUpdate($listingId);
            if ((int) $listing['account_old'] !== $sellerId) {
                throw new RuntimeException('Only the seller can cancel this listing.');
            }
            if ((int) $listing['status'] !== self::STATUS_ACTIVE || !$this->isListingOpen($listingId)) {
                throw new RuntimeException('This listing can no longer be cancelled.');
            }
            if ((int) $listing['bid_account'] > 0) {
                throw new RuntimeException('A listing with a bid cannot be cancelled.');
            }

            $this->returnCharacterToSeller($listing);
            $this->db->exec(
                'UPDATE `myaac_charbazaar` SET `status` = ' . self::STATUS_CANCELLED . ', `cancelled_at` = NOW() WHERE `id` = ' . $listingId
            );
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function settleExpiredListings(): int
    {
        if (!$this->isInstalled()) {
            return 0;
        }

        $expired = $this->db->query(
            'SELECT `id` FROM `myaac_charbazaar` WHERE `status` = ' . self::STATUS_ACTIVE . ' AND `date_end` <= NOW()'
        )->fetchAll();
        $settled = 0;

        foreach ($expired as $row) {
            $this->db->beginTransaction();
            try {
                $listing = $this->listingForUpdate((int) $row['id']);
                if ((int) $listing['status'] !== self::STATUS_ACTIVE || $this->isListingOpen((int) $listing['id'])) {
                    $this->db->commit();
                    continue;
                }

                if ((int) $listing['bid_account'] > 0 && (int) $listing['bid_price'] > 0) {
                    $this->completeSale($listing);
                } else {
                    $this->returnCharacterToSeller($listing);
                    $this->db->exec(
                        'UPDATE `myaac_charbazaar` SET `status` = ' . self::STATUS_EXPIRED . ', `completed_at` = NOW() WHERE `id` = ' . (int) $listing['id']
                    );
                }
                $this->db->commit();
                $settled++;
            } catch (Throwable $exception) {
                $this->rollback();
                error_log('Zealot Market settlement failed for listing ' . (int) $row['id'] . ': ' . $exception->getMessage());
            }
        }

        return $settled;
    }

    public function minimumBidFor(array $listing): int
    {
        $currentBid = max(
            (int) ($listing['starting_price'] ?? 0),
            (int) ($listing['bid_price'] ?? 0),
            (int) ($listing['price'] ?? 0)
        );

        return $currentBid + max(1, (int) ($this->config['bazaar_bid'] ?? 1));
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_SOLD => 'Sold',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_EXPIRED => 'Expired',
            default => 'Live listing',
        };
    }

    /** Decode the immutable state saved when a character was listed. */
    public static function decodeCharacterSnapshot($encoded): ?array
    {
        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        $snapshot = json_decode($encoded, true);
        return is_array($snapshot) && isset($snapshot['player'], $snapshot['items']) ? $snapshot : null;
    }

    public static function snapshotEquipment(?array $snapshot): array
    {
        $equipment = [];
        foreach (($snapshot['items']['player_items'] ?? []) as $item) {
            $slot = (int) ($item['pid'] ?? 0);
            if ($slot >= 1 && $slot <= 10 && (int) ($item['itemtype'] ?? 0) > 0) {
                $equipment[$slot] = $item;
            }
        }

        return $equipment;
    }

    /** Build a bounded visual tree from the server's parent/sid item rows. */
    public static function snapshotItemTree(?array $snapshot, string $source, array $rootPids): array
    {
        $items = $snapshot['items'][$source] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $children = [];
        foreach ($items as $item) {
            if (!is_array($item) || (int) ($item['itemtype'] ?? 0) <= 0) {
                continue;
            }
            $children[(int) ($item['pid'] ?? 0)][] = $item;
        }

        $build = static function (array $item, array $seen = []) use (&$build, $children): array {
            $sid = (int) ($item['sid'] ?? 0);
            if ($sid <= 0 || isset($seen[$sid])) {
                $item['children'] = [];
                return $item;
            }

            $seen[$sid] = true;
            $item['children'] = [];
            foreach ($children[$sid] ?? [] as $child) {
                $item['children'][] = $build($child, $seen);
            }
            return $item;
        };

        $roots = [];
        foreach ($rootPids as $pid) {
            foreach ($children[(int) $pid] ?? [] as $item) {
                $roots[] = $build($item);
            }
        }

        return $roots;
    }

    public static function snapshotOutfitUrl(?array $snapshot): ?string
    {
        $path = $snapshot['player']['outfit']['sprite'] ?? null;
        if (!is_string($path) || strpos($path, 'images/library/market-outfits/') !== 0 || strpos($path, '..') !== false) {
            return null;
        }

        return is_file(BASE . $path) ? $path : null;
    }

    /**
     * Hides a completed listing only from one account's Market History.
     * The listing, ownership transfer and financial ledger remain intact.
     */
    public function hideHistoryListing(int $accountId, int $listingId): void
    {
        if ($accountId < 1 || $listingId < 1) {
            throw new RuntimeException('The history entry could not be hidden.');
        }

        $this->db->beginTransaction();
        try {
            if (!$this->accountForUpdate($accountId)) {
                throw new RuntimeException('Your account could not be found.');
            }

            $listing = $this->db->query(
                'SELECT `id`, `status` FROM `myaac_charbazaar` WHERE `id` = ' . $listingId . ' FOR UPDATE'
            )->fetch();
            if (!$listing || (int) $listing['status'] === self::STATUS_ACTIVE) {
                throw new RuntimeException('Only completed Market listings can be hidden from history.');
            }

            $this->db->exec(
                'INSERT IGNORE INTO `myaac_zealot_market_hidden_history` (`account_id`, `auction_id`, `created_at`) VALUES ('
                . $accountId . ', ' . $listingId . ', NOW())'
            );
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function completeSale(array $listing): void
    {
        $listingId = (int) $listing['id'];
        $buyerId = (int) $listing['bid_account'];
        $amount = (int) $listing['bid_price'];
        $tax = (int) floor($amount * ((int) $listing['tax_rate']) / 100);
        $payout = $amount - $tax;
        $escrowId = (int) $listing['escrow_account_id'];

        $player = $this->db->query('SELECT `account_id` FROM `players` WHERE `id` = ' . (int) $listing['player_id'] . ' FOR UPDATE')->fetch();
        if (!$player || (int) $player['account_id'] !== $escrowId) {
            throw new RuntimeException('The character is not held by the Zealot Market escrow account.');
        }

        $this->accountForUpdate($buyerId);
        $this->accountForUpdate((int) $listing['account_old']);
        $this->db->exec('UPDATE `players` SET `account_id` = ' . $buyerId . ' WHERE `id` = ' . (int) $listing['player_id']);
        $this->creditCoins((int) $listing['account_old'], $payout, 'sale_payout', $listingId, 'Character sale payout');
        $this->ledger((int) $listing['account_old'], $payout, 'sale_payout', $listingId, 'Character sale payout');
        $this->db->exec(
            'UPDATE `myaac_charbazaar` SET `status` = ' . self::STATUS_SOLD . ', `account_new` = ' . $buyerId
            . ', `final_price` = ' . $amount . ', `completed_at` = NOW() WHERE `id` = ' . $listingId
        );
    }

    private function assertListingIsBiddable(array $listing, int $buyerId): void
    {
        if ((int) $listing['status'] !== self::STATUS_ACTIVE || !$this->isListingOpen((int) $listing['id'])) {
            throw new RuntimeException('This character listing is no longer active.');
        }
        if ((int) $listing['account_old'] === $buyerId) {
            throw new RuntimeException('You cannot bid on your own character listing.');
        }
        if ((int) $listing['escrow_account_id'] <= 0) {
            throw new RuntimeException('This listing is missing its escrow lock and cannot receive bids.');
        }
    }

    private function returnCharacterToSeller(array $listing): void
    {
        $player = $this->db->query('SELECT `account_id` FROM `players` WHERE `id` = ' . (int) $listing['player_id'] . ' FOR UPDATE')->fetch();
        if (!$player || (int) $player['account_id'] !== (int) $listing['escrow_account_id']) {
            throw new RuntimeException('The character is not held by the Zealot Market escrow account.');
        }
        $this->db->exec('UPDATE `players` SET `account_id` = ' . (int) $listing['account_old'] . ' WHERE `id` = ' . (int) $listing['player_id']);
    }

    private function listingForUpdate(int $listingId): array
    {
        $listing = $this->db->query('SELECT * FROM `myaac_charbazaar` WHERE `id` = ' . $listingId . ' FOR UPDATE')->fetch();
        if (!$listing) {
            throw new RuntimeException('This character listing could not be found.');
        }

        return $listing;
    }

    /**
     * Expiry checks use the database clock, avoiding a mismatch between the
     * PHP and MySQL time zones that could settle listings too early or late.
     */
    private function isListingOpen(int $listingId): bool
    {
        return (bool) $this->db->query(
            'SELECT `id` FROM `myaac_charbazaar` WHERE `id` = ' . $listingId . ' AND `date_end` > NOW()'
        )->fetch();
    }

    private function characterForUpdate(int $playerId): ?array
    {
        $columns = [
            'id', 'account_id', 'name', 'level', 'vocation', 'sex', 'cap', 'health', 'healthmax', 'mana', 'manamax', 'maglevel', 'soul',
            'skill_fist', 'skill_club', 'skill_sword', 'skill_axe', 'skill_dist', 'skill_shielding', 'skill_fishing',
            'blessings1', 'blessings2', 'blessings3', 'blessings4', 'blessings5', 'blessings6', 'blessings7', 'blessings8',
            'looktype', 'lookaddons', 'lookhead', 'lookbody', 'looklegs', 'lookfeet',
        ];
        $available = [];
        foreach ($columns as $column) {
            if ($this->db->hasColumn('players', $column)) {
                $available[] = '`' . $column . '`';
            }
        }

        $player = $this->db->query(
            'SELECT ' . implode(', ', $available) . ' FROM `players` WHERE `id` = ' . $playerId . ' FOR UPDATE'
        )->fetch();

        return $player ?: null;
    }

    private function isPlayerOnline(int $playerId): bool
    {
        if ($this->db->hasTable('players_online')) {
            return (bool) $this->db->query(
                'SELECT `player_id` FROM `players_online` WHERE `player_id` = ' . $playerId . ' LIMIT 1'
            )->fetch();
        }

        return $this->db->hasColumn('players', 'online') && (bool) $this->db->query(
            'SELECT `id` FROM `players` WHERE `id` = ' . $playerId . ' AND `online` = 1 LIMIT 1'
        )->fetch();
    }

    private function captureCharacterSnapshot(array $player): array
    {
        $outfit = [
            'looktype' => (int) ($player['looktype'] ?? 0),
            'addons' => (int) ($player['lookaddons'] ?? 0),
            'head' => (int) ($player['lookhead'] ?? 0),
            'body' => (int) ($player['lookbody'] ?? 0),
            'legs' => (int) ($player['looklegs'] ?? 0),
            'feet' => (int) ($player['lookfeet'] ?? 0),
        ];
        $outfit['sprite'] = $this->outfitSpritePath($outfit);

        return [
            'version' => 1,
            'captured_at' => time(),
            'player' => [
                'id' => (int) ($player['id'] ?? 0),
                'name' => (string) ($player['name'] ?? ''),
                'level' => (int) ($player['level'] ?? 1),
                'vocation' => (int) ($player['vocation'] ?? 0),
                'sex' => (int) ($player['sex'] ?? 0),
                'cap' => (int) ($player['cap'] ?? 0),
                'outfit' => $outfit,
            ] + array_intersect_key($player, array_flip([
                'health', 'healthmax', 'mana', 'manamax', 'maglevel', 'soul', 'skill_fist', 'skill_club', 'skill_sword',
                'skill_axe', 'skill_dist', 'skill_shielding', 'skill_fishing', 'blessings1', 'blessings2', 'blessings3',
                'blessings4', 'blessings5', 'blessings6', 'blessings7', 'blessings8',
            ])),
            'items' => [
                'player_items' => $this->snapshotItemRows('player_items', (int) $player['id']),
                'depot_items' => $this->snapshotItemRows('player_depotitems', (int) $player['id']),
                'inbox_items' => $this->snapshotItemRows('player_inboxitems', (int) $player['id']),
            ],
        ];
    }

    private function snapshotItemRows(string $table, int $playerId): array
    {
        if (!$this->db->hasTable($table)) {
            return [];
        }

        $items = [];
        foreach ($this->db->query(
            'SELECT `pid`, `sid`, `itemtype`, `count`, `attributes` FROM `' . $table . '` WHERE `player_id` = ' . $playerId . ' ORDER BY `pid`, `sid`'
        ) as $item) {
            $items[] = [
                'pid' => (int) $item['pid'],
                'sid' => (int) $item['sid'],
                'itemtype' => (int) $item['itemtype'],
                'count' => max(1, (int) $item['count']),
                // Attributes are retained for a verifiable snapshot, although
                // the public listing intentionally renders only item metadata.
                'attributes' => base64_encode((string) ($item['attributes'] ?? '')),
            ];
        }

        return $items;
    }

    private function outfitSpritePath(array $outfit): string
    {
        $signature = implode(':', [
            (int) $outfit['looktype'], (int) $outfit['addons'], (int) $outfit['head'],
            (int) $outfit['body'], (int) $outfit['legs'], (int) $outfit['feet'],
        ]);

        return 'images/library/market-outfits/' . hash('sha256', $signature) . '.png';
    }

    private function exportSnapshotOutfit(array $snapshot): void
    {
        $outfit = $snapshot['player']['outfit'] ?? [];
        $output = $outfit['sprite'] ?? null;
        if (!is_array($outfit) || !is_string($output) || (int) ($outfit['looktype'] ?? 0) <= 0) {
            return;
        }

        $outputPath = BASE . $output;
        if (is_file($outputPath)) {
            return;
        }

        $assetsPath = getenv('ZEALOT_OTCLIENT_ASSETS');
        if (!$assetsPath) {
            $versions = glob(dirname(BASE) . '/otclient/data/things/*', GLOB_ONLYDIR) ?: [];
            usort($versions, static fn($left, $right) => strnatcasecmp(basename($right), basename($left)));
            foreach ($versions as $version) {
                if (is_file($version . '/catalog-content.json')) {
                    $assetsPath = $version;
                    break;
                }
            }
        }

        $exporter = SYSTEM . 'bin/export_otclient_sprite.py';
        if (!is_file($exporter) || !is_string($assetsPath) || !is_file(rtrim($assetsPath, '/') . '/catalog-content.json')) {
            error_log('Zealot Market outfit preview was skipped: OTClient assets are unavailable.');
            return;
        }
        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            error_log('Zealot Market outfit preview could not create its cache directory.');
            return;
        }

        $arguments = [
            'python3', $exporter, '--assets', rtrim($assetsPath, '/'), '--looktype', (string) (int) $outfit['looktype'],
            '--output', $outputPath, '--direction', '2', '--head', (string) (int) ($outfit['head'] ?? 0),
            '--body', (string) (int) ($outfit['body'] ?? 0), '--legs', (string) (int) ($outfit['legs'] ?? 0),
            '--feet', (string) (int) ($outfit['feet'] ?? 0), '--addons', (string) (int) ($outfit['addons'] ?? 0),
        ];
        $command = implode(' ', array_map('escapeshellarg', $arguments));
        exec($command . ' 2>&1', $ignoredOutput, $status);
        if ($status !== 0) {
            error_log('Zealot Market outfit preview export failed for looktype ' . (int) $outfit['looktype'] . '.');
        }
    }

    private function accountForUpdate(int $accountId): ?array
    {
        $account = $this->db->query(
            'SELECT `id`, `coins_transferable` FROM `accounts` WHERE `id` = ' . $accountId . ' FOR UPDATE'
        )->fetch();

        return $account ?: null;
    }

    private function debitCoins(int $accountId, int $amount, string $type, ?int $listingId, string $description): void
    {
        if ($amount <= 0) {
            return;
        }
        $account = $this->accountForUpdate($accountId);
        if (!$account || (int) $account['coins_transferable'] < $amount) {
            throw new RuntimeException('You do not have enough transferable Zealot Coins.');
        }
        $this->db->exec(
            'UPDATE `accounts` SET `coins_transferable` = `coins_transferable` - ' . $amount . ' WHERE `id` = ' . $accountId
        );
    }

    private function creditCoins(int $accountId, int $amount, string $type, ?int $listingId, string $description): void
    {
        if ($amount <= 0) {
            return;
        }
        if (!$this->accountForUpdate($accountId)) {
            throw new RuntimeException('A Market account could not be found.');
        }
        $this->db->exec(
            'UPDATE `accounts` SET `coins_transferable` = `coins_transferable` + ' . $amount . ' WHERE `id` = ' . $accountId
        );
    }

    private function ledger(int $accountId, int $amount, string $type, ?int $listingId, string $description): void
    {
        $balance = $this->db->query('SELECT `coins_transferable` FROM `accounts` WHERE `id` = ' . $accountId)->fetch();
        $this->db->exec(
            'INSERT INTO `myaac_zealot_market_ledger` (`account_id`, `auction_id`, `entry_type`, `amount`, `balance_after`, `description`, `created_at`) VALUES ('
            . $accountId . ', ' . ($listingId === null ? 'NULL' : (int) $listingId) . ', '
            . $this->db->quote($type) . ', ' . $amount . ', ' . (int) ($balance['coins_transferable'] ?? 0) . ', '
            . $this->db->quote($description) . ', NOW())'
        );
    }

    private function escrowAccountId(): int
    {
        $account = $this->db->query("SELECT `id` FROM `accounts` WHERE `name` = 'zealot_market_escrow' FOR UPDATE")->fetch();
        if ($account) {
            return (int) $account['id'];
        }

        $password = hash('sha256', bin2hex(random_bytes(32)));
        $this->db->exec(
            "INSERT INTO `accounts` (`name`, `password`, `email`, `key`, `created`, `rlname`, `location`, `country`) VALUES ('zealot_market_escrow', "
            . $this->db->quote($password) . ", 'market@localhost.invalid', '', " . time() . ", 'Zealot Market', '', '')"
        );

        return (int) $this->db->lastInsertId();
    }

    private function taxRate(): int
    {
        return max(0, min(100, (int) ($this->config['bazaar_tax'] ?? 0)));
    }

    private function rollback(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}
