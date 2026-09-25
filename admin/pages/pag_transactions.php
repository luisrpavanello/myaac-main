<?php
global $db;

/**
 * Lista de donates
 *
 * @package   MyAAC
 * @author    Elson
 * @author    OpenTibiaBR
 * @copyright 2023 MyAAC
 * @link      https://github.com/opentibiabr/myaac
 */
defined('MYAAC') or die('Direct access not allowed!');
if (!$db->hasTable('myaac_stripe_transactions')) {
    die('USD payment transactions table does not exist yet.');
}

$count = $db->query("SELECT `id` FROM `myaac_stripe_transactions` WHERE `status` = 'paid'")->rowCount();
$title = "$count USD payments so far";
$base = BASE_URL . 'admin/?p=pag_transactions';
$donates = $db->query('SELECT * FROM `myaac_stripe_transactions` ORDER BY `id` DESC')->fetchAll();
?>
<div class="row">
    <div class="col-md-12">
        <div class="box">
            <div class="box-body no-padding">
                <table id="tb_donates" class="table table-striped">
                    <tbody>
                    <tr>
                        <th style="width: 40px">#</th>
                        <th style="width: 60px">ID</th>
                        <th style="width: 140px;">Transaction</th>
                        <th>Account & Players</th>
                        <th style="width: 160px; text-align: center">Amount / Reward</th>
                        <th style="width: 100px; text-align: center">Method</th>
                        <th style="width: 90px; text-align: center">Product</th>
                        <th style="width: 70px; text-align: center">Status</th>
                        <th style="width: 90px; text-align: center">Delivered</th>
                        <th style="width: 160px;">Created</th>
                    </tr>
                    <?php foreach ($donates as $k => $donate) {
                        $account = $db->query('SELECT `id`, `email` FROM `accounts` WHERE `id` = ' . (int) $donate['account_id'] . ' LIMIT 1')->fetch();
                        $players = getPlayerByAccountId((int) $donate['account_id']);
                        $reward = $donate['product_type'] === 'premium'
                            ? (int) $donate['premium_days'] . ' premium days'
                            : (int) $donate['coins'] . ' coins';
                        ?>
                        <tr style="background-color: <?= $donate['status'] !== 'paid' ? '#502a2a' : '' ?>">
                            <td><?= $k + 1 ?></td>
                            <td><?= $donate['id'] ?></td>
                            <td><small><?= htmlspecialchars($donate['payment_intent_id'] ?: $donate['checkout_session_id']) ?></small></td>
                            <td><?= htmlspecialchars($account['email'] ?? 'Deleted account') ?> (<?= $players ?>)</td>
                            <td style="text-align: center">
                                USD <?= number_format((float) $donate['amount_total'] / 100, 2, '.', ',') ?>
                                (<?= htmlspecialchars($reward) ?>)
                            </td>
                            <td style="text-align: center">Stripe</td>
                            <td style="text-align: center"><?= htmlspecialchars($donate['product_type']) ?></td>
                            <td style="text-align: center"><?= htmlspecialchars($donate['status']) ?></td>
                            <td style="text-align: center"><?= $donate['delivered_at'] ? htmlspecialchars($donate['delivered_at']) : '-' ?></td>
                            <td><?= htmlspecialchars($donate['created_at']) ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        $('#tb_donates').DataTable()
    })
</script>
