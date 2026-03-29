<?php
get_header();

// Retrieve saved attachment IDs and pick one at random.
$dracka_404_ids = (array) get_option('dracka_404_image_ids', []);
$dracka_404_ids = array_values(array_filter($dracka_404_ids, function ($id) {
    return absint($id) > 0 && get_post_type((int) $id) === 'attachment';
}));

shuffle($dracka_404_ids);
$dracka_404_id = ! empty($dracka_404_ids) ? absint($dracka_404_ids[0]) : 0;
?>

<main class="error-404">
    <?php if ($dracka_404_id > 0) :
        $dracka_404_img_url = wp_get_attachment_image_url($dracka_404_id, 'full');
        $dracka_404_alt     = (string) get_post_meta($dracka_404_id, '_wp_attachment_image_alt', true);
        if ($dracka_404_img_url) : ?>
            <a
                class="error-404__image-link"
                href="<?php echo esc_url(home_url('/')); ?>"
                aria-label="<?php esc_attr_e('Back to home', 'dracka'); ?>">
                <img
                    class="error-404__image"
                    src="<?php echo esc_url($dracka_404_img_url); ?>"
                    alt="<?php echo esc_attr($dracka_404_alt); ?>">
            </a>
        <?php endif; ?>
    <?php else : ?>
        <div class="error-404__fallback">
            <h1 class="error-404__title"><?php esc_html_e('Page not found', 'dracka'); ?></h1>
            <a class="error-404__home-link" href="<?php echo esc_url(home_url('/')); ?>">
                <?php esc_html_e('Back to home', 'dracka'); ?>
            </a>
        </div>
    <?php endif; ?>
</main>

<?php
get_footer();
