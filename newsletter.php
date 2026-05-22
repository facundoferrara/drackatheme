<?php

/**
 * Template Name: Newsletter
 */

get_header();

$form_id = (int) get_option('dracka_mailpoet_form_id', 0);
?>

<main class="newsletter-page">
    <h1>Newsletter</h1>

    <?php if ($form_id > 0) : ?>
        <div class="newsletter-form">
            <?php echo do_shortcode('[mailpoet_form id="' . $form_id . '"]'); ?>
        </div>
    <?php else : ?>
        <p>Subscription form coming soon.</p>
    <?php endif; ?>
</main>

<?php
get_footer();
