<footer class="site-footer">
    <div class="site-footer__inner">
        <?php if (is_active_sidebar('footer-content')) : ?>
            <?php dynamic_sidebar('footer-content'); ?>
        <?php else : ?>
            <p class="site-footer__fallback">© <?php echo esc_html(gmdate('Y')); ?> <?php bloginfo('name'); ?></p>
        <?php endif; ?>

        <?php if (has_nav_menu('social')) : ?>
            <nav class="site-footer__social" aria-label="Social links">
                <?php
                wp_nav_menu([
                    'theme_location' => 'social',
                    'container'      => false,
                    'menu_class'     => 'social-menu',
                    'link_before'    => '<span class="social-icon">',
                    'link_after'     => '</span>',
                ]);
                ?>
            </nav>
        <?php endif; ?>
    </div>
</footer>

<?php wp_footer(); ?>
</body>

</html>