<?php
/**
 * Template Name: فريق العمل
 *
 * @package Magdi_Hilal_Adco
 */
get_header();
$team = mha_query_posts('mha_team', 30);
?>
<section class="mha-page-head">
    <div class="container">
        <h1>فريق العمل</h1>
        <p>نحو 20 إلى 30 محاسباً — هذه بطاقات نموذجية تُستبدل بأسماء الزملاء لاحقاً.</p>
    </div>
</section>
<section class="mha-section">
    <div class="container">
        <div class="row">
            <?php
            $people = $team ?: [];
            if (!$people) {
                foreach (mha_placeholder_team() as $row) {
                    $people[] = (object) ['post_title' => $row[0], 'post_content' => $row[1], 'ID' => 0];
                }
            }
            foreach ($people as $member) :
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <article class="mha-team-card">
                        <div class="mha-avatar"><?php echo esc_html(mb_substr($member->post_title, 0, 1)); ?></div>
                        <h3><?php echo esc_html($member->post_title); ?></h3>
                        <p><?php echo esc_html(wp_strip_all_tags($member->post_content)); ?></p>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php get_footer();
