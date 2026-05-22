<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__grid">
            <?php
            $footer_rows = [
                ['footer-top-left',    'footer-top-right'],
                ['footer-center-left', 'footer-center-right'],
                ['footer-bottom-left', 'footer-bottom-right'],
            ];
            foreach ($footer_rows as [$left, $right]) :
                $has_left  = is_active_sidebar($left);
                $has_right = is_active_sidebar($right);
                if ($has_left xor $has_right) :
                    $solo = $has_left ? $left : $right;
            ?>
                    <div class="footer-cell footer-cell--span">
                        <?php dynamic_sidebar($solo); ?>
                    </div>
            <?php
                else :
                    dynamic_sidebar($left);
                    dynamic_sidebar($right);
                endif;
            endforeach;
            ?>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>

</html>