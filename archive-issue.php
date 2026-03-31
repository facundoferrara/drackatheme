<?php
get_header();

$active_tab = dracka_get_library_tab();
if ($active_tab === 'series') {
    $active_tab = 'issues';
}
?>

<main class="issue-archive">
    <h1>Issues</h1>

    <?php get_template_part('template-parts/archive-tabs'); ?>

    <?php if (have_posts()) : ?>
        <div class="issue-grid">
            <?php while (have_posts()) : the_post(); ?>
                <?php $premiere_badge_markup = dracka_get_premiere_badge_markup(get_the_ID(), 10); ?>
                <article <?php post_class('issue-card'); ?>>
                    <?php if ($premiere_badge_markup !== '') : ?>
                        <div class="card-badges card-badges--ribbon"><?php echo wp_kses_post($premiere_badge_markup); ?></div>
                    <?php endif; ?>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="issue-thumb"><?php the_post_thumbnail('medium'); ?></div>
                    <?php endif; ?>
                    <div class="issue-excerpt"><?php the_excerpt(); ?></div>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p>No issues yet.</p>
    <?php endif; ?>
</main>

<?php
get_footer();
