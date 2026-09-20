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
        return $this->db->hasTable('myaac_charbazaar')
            && $this->db->hasTable('myaac_charbazaar_bid')
            && $this->db->hasTable('myaac_zealot_market_ledger');
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

            $player = $this->db->query(
                'SELECT `id`, `account_id`, `name` FROM `players` WHERE `id` = ' . $playerId . ' FOR UPDATE'
            )->fetch();
            if (!$player || (int) $player['account_id'] !== $sellerId) {
                throw new RuntimeException('You can only list a character that belongs to your account.');
            }

            $active = $this->db->query(
                'SELECT `id` FROM `myaac_charbazaar` WHERE `player_id` = ' . $playerId
                . ' AND `status` = ' . self::STATUS_ACTIVE . ' FOR UPDATE'
            )->fetch();
            if ($active) {
                throw new RuntimeException('This character already has an active market listing.');
            }

            $escrowId = $this->escrowAccountId();
            $this->debitCoins($sellerId, $listingFee, 'listing_fee', null, 'Listing fee for ' . $player['name']);

            $this->db->exec(
                'UPDATE `players` SET `account_id` = ' . $escrowId . ' WHERE `id` = ' . $playerId
            );

            $this->db->exec(
                'INSERT INTO `myaac_charbazaar` (`account_old`, `account_new`, `player_id`, `price`, `starting_price`, `date_end`, `date_start`, `bid_account`, `bid_price`, `status`, `escrow_account_id`, `listing_fee`, `tax_rate`) VALUES ('
                . $sellerId . ', 0, ' . $playerId . ', ' . $startingPrice . ', ' . $startingPrice . ', '
                . 'DATE_ADD(NOW(), INTERVAL ' . $days . ' DAY), NOW(), 0, 0, ' . self::STATUS_ACTIVE . ', '
                . $escrowId . ', ' . $listingFee . ', ' . $this->taxRate() . ')'
            );
            $listingId = (int) $this->db->lastInsertId();
            $this->ledger($sellerId, -$listingFee, 'listing_fee', $listingId, 'Listing fee');
            $this->db->commit();

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
            if ($amount <= $currentBid) {
                throw new RuntimeException('Your bid must be higher than the current bid of ' . number_format($currentBid) . ' transferable Zealot Coins.');
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
