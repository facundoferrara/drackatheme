<footer class="site-footer site-footer--mobile">
    <div class="site-footer__inner">
        <div class="site-footer__grid">
            <?php
            $footer_rows = [
                ['footer-top-left',    'footer-top-right'],
                ['footer-center-left', 'footer-center-right'],
                ['footer-bottom-left', 'footer-bottom-right'],
            ];
            foreach ($footer_rows as [$left, $right]) :
                ob_start();
                dynamic_sidebar($left);
                $left_html  = trim(ob_get_clean());
                ob_start();
                dynamic_sidebar($right);
                $right_html = trim(ob_get_clean());
                $sidebar_has_content = fn($html) => trim(strip_tags($html)) !== ''
                    || (bool) preg_match('/<(img|svg|video|audio|iframe|canvas|picture)\b/i', $html);
                $has_left  = $sidebar_has_content($left_html);
                $has_right = $sidebar_has_content($right_html);
                if (! $has_left && ! $has_right) : continue;
                endif;
                if ($has_left xor $has_right) :
                    $solo_html = $has_left ? $left_html : $right_html;
                    echo '<div class="footer-cell footer-cell--span">' . $solo_html . '</div>';
                else :
                    echo '<div class="footer-cell">' . $left_html  . '</div>';
                    echo '<div class="footer-cell">' . $right_html . '</div>';
                endif;
            endforeach;
            ?>
        </div>
    </div>
</footer>

<?php if (is_active_sidebar('footer-desktop-left') || is_active_sidebar('footer-desktop-center') || is_active_sidebar('footer-desktop-right')) : ?>
    <footer class="site-footer site-footer--desktop">
        <div class="site-footer__inner">
            <div class="site-footer__grid">
                <?php foreach (['footer-desktop-left', 'footer-desktop-center', 'footer-desktop-right'] as $col) : ?>
                    <div class="footer-cell">
                        <?php dynamic_sidebar($col); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>

</html>