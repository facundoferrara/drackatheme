<?php
get_header();
$series_id = (int) get_post_meta(get_the_ID(), 'dracka_series_id', true);
$series_link = $series_id ? get_permalink($series_id) : '';
$issue_id = get_the_ID();
$access_mode = dracka_get_issue_access_mode($issue_id);
$flipbook_id = (int) get_post_meta($issue_id, DRACKA_ISSUE_FLIPBOOK_ID_META_KEY, true);
$patreon_url = (string) get_post_meta($issue_id, DRACKA_ISSUE_PATREON_URL_META_KEY, true);
$patreon_image_id = (int) get_post_meta($issue_id, DRACKA_ISSUE_PATREON_IMAGE_META_KEY, true);
?>

<main class="issue-single">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <h1><?php the_title(); ?></h1>

                <?php if ($series_link) : ?>
                    <p class="issue-series">Series: <a href="<?php echo esc_url($series_link); ?>"><?php echo esc_html(get_the_title($series_id)); ?></a></p>
                <?php endif; ?>

                <div class="issue-content">
                    <?php
                    if ($access_mode === 'patreon') {
                        if ($patreon_image_id > 0) {
                            echo '<div class="issue-patreon-image">';
                            if ($patreon_url !== '') {
                                echo '<a href="' . esc_url($patreon_url) . '" target="_blank" rel="noopener noreferrer">';
                                echo wp_get_attachment_image($patreon_image_id, 'large');
                                echo '</a>';
                            } else {
                                echo wp_get_attachment_image($patreon_image_id, 'large');
                            }
                            echo '</div>';
                        }
                    } elseif ($flipbook_id > 0 && shortcode_exists('dflip')) {
                        echo do_shortcode('[dflip id="' . esc_attr((string) $flipbook_id) . '"]');
                    } elseif ($flipbook_id > 0) {
                        echo '<div class="issue-error">';
                        echo '<p><strong>DearFlip Flipbook Plugin Not Available</strong></p>';
                        echo '<p>The DearFlip plugin is required to display this issue as a flipbook. Please ensure the plugin is installed and activated.</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="issue-error">';
                        echo '<p><strong>Flipbook Not Configured</strong></p>';
                        echo '<p>This issue has no selected DearFlip book yet.</p>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php get_template_part('template-parts/comments-box', null, ['initially_open' => false]); ?>

<?php
get_footer();
