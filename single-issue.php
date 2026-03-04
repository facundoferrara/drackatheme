<?php
get_header();
$series_id = (int) get_post_meta(get_the_ID(), 'dracka_series_id', true);
$series_link = $series_id ? get_permalink($series_id) : '';
$issue_id = get_the_ID();
$pdf_attachment_id = dracka_get_issue_pdf($issue_id);
?>

<main class="issue-single">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <div class="card-badges">
                    <span class="content-badge">Issue</span>
                    <?php if (!$series_id) : ?>
                        <span class="content-badge content-badge--muted">Standalone</span>
                    <?php endif; ?>
                </div>
                <h1><?php the_title(); ?></h1>

                <?php if ($series_link) : ?>
                    <p class="issue-series">Series: <a href="<?php echo esc_url($series_link); ?>"><?php echo esc_html(get_the_title($series_id)); ?></a></p>
                <?php endif; ?>

                <div class="issue-content">
                    <?php
                    // Render DearFlip flipbook if PDF is attached and DearFlip plugin is active
                    if ($pdf_attachment_id && shortcode_exists('dflip')) {
                        $pdf_url = dracka_get_issue_pdf_url($issue_id);
                        if ($pdf_url) {
                            echo do_shortcode('[dflip source="' . esc_attr($pdf_url) . '"]');
                        } else {
                            echo '<div class="issue-error">';
                            echo '<p><strong>PDF Not Found</strong></p>';
                            echo '<p>The PDF file for this issue could not be found.</p>';
                            echo '</div>';
                        }
                    } elseif ($pdf_attachment_id) {
                        // PDF is attached but DearFlip is not available
                        echo '<div class="issue-error">';
                        echo '<p><strong>DearFlip Flipbook Plugin Not Available</strong></p>';
                        echo '<p>The DearFlip plugin is required to display this issue as a flipbook. Please ensure the plugin is installed and activated.</p>';
                        echo '</div>';
                    } else {
                        // No PDF attached; fallback to regular content
                        the_content();
                    }
                    ?>
                </div>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php
get_footer();
