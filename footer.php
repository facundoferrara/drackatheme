<footer class="site-footer">
    <div class="site-footer__inner">
        <?php if (is_active_sidebar('footer-content')) : ?>
            <?php dynamic_sidebar('footer-content'); ?>
        <?php else : ?>
            <p class="site-footer__fallback">© <?php echo esc_html(gmdate('Y')); ?> <?php bloginfo('name'); ?></p>
        <?php endif; ?>
    </div>
</footer>

<?php wp_footer(); ?>
</body>

</html>