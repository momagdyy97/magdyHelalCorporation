<?php
/**
 * Template Name: مشاريعنا
 *
 * @package Magdi_Hilal_Adco
 */
get_header();
$projects = mha_query_posts('mha_project', 12);
?>
<section class="mha-page-head">
    <div class="container">
        <h1>مشاريعنا</h1>
        <p>نماذج لأعمال يمكن استبدالها بملفات حقيقية دون ذكر أسرار العملاء.</p>
    </div>
</section>
<section class="mha-section">
    <div class="container">
        <div class="row">
            <?php if ($projects) : ?>
                <?php foreach ($projects as $project) : ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <article class="mha-news-card">
                            <img src="<?php echo esc_url(mha_img('service-audit.png')); ?>" alt="">
                            <div class="mha-news-body">
                                <time><?php echo esc_html($project->post_excerpt); ?></time>
                                <h3><?php echo esc_html(get_the_title($project)); ?></h3>
                                <p><?php echo esc_html(wp_trim_words($project->post_content, 24)); ?></p>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <?php foreach (mha_placeholder_projects() as $project) : ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <article class="mha-news-card">
                            <img src="<?php echo esc_url(mha_img('hero-1.png')); ?>" alt="">
                            <div class="mha-news-body">
                                <time><?php echo esc_html($project[1] . ' — ' . $project[2]); ?></time>
                                <h3><?php echo esc_html($project[0]); ?></h3>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php get_footer();
