<?php
/**
 * Clients logo carousel.
 *
 * @package Magdi_Hilal_Adco
 */
$clients = mha_query_posts('mha_client', 12);
if (!$clients) {
    $clients = [];
}
?>
<section class="mha-section mha-clients" id="clients">
    <div class="container">
        <div class="mha-section-head">
            <h2>عملاؤنا</h2>
            <p>نماذج لأسماء يمكن استبدالها بشعارات العملاء الفعليين لاحقاً.</p>
        </div>
        <div id="mhaClients" class="carousel slide" data-ride="carousel" data-interval="5000">
            <div class="carousel-inner">
                <?php
                $chunks = $clients ? array_chunk($clients, 4) : [mha_placeholder_clients()];
                foreach ($chunks as $i => $chunk) :
                    ?>
                    <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>">
                        <div class="row justify-content-center">
                            <?php foreach ($chunk as $client) : ?>
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="mha-client-logo">
                                        <?php if (is_object($client) && has_post_thumbnail($client)) : ?>
                                            <?php echo get_the_post_thumbnail($client, 'medium'); ?>
                                        <?php else : ?>
                                            <?php
                                            $name = is_object($client) ? get_the_title($client) : $client[0];
                                            $short = is_object($client) ? ($client->post_excerpt ?: $name) : $client[1];
                                            ?>
                                            <span class="mha-client-mark"><?php echo esc_html(mb_substr($name, 0, 1)); ?></span>
                                            <strong><?php echo esc_html($name); ?></strong>
                                            <small><?php echo esc_html($short); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <ol class="carousel-indicators mha-dots">
                <?php foreach ($chunks as $i => $chunk) : ?>
                    <li data-target="#mhaClients" data-slide-to="<?php echo (int) $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>"></li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
</section>
