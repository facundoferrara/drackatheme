<?php
get_header();

$active_tab = dracka_get_library_tab();

$empty_messages = [
    'series'      => 'No series yet.',
    'issues'      => 'No issues yet.',
    'standalones' => 'No standalone issues yet.',
];

$archive_class = 'library-grid';
if ($active_tab === 'issues') {
    $archive_class = 'issue-grid';
}
?>

<main class="library-archive">
    <h1>Library</h1>

    <?php get_template_part('template-parts/archive-tabs'); ?>

    <div class="library-search is-hidden">
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" aria-label="Library search">
            <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Search the library">
            <input type="hidden" name="dracka_scope" value="library">
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if (have_posts()) : ?>
        <div class="<?php echo esc_attr($archive_class); ?>">
            <?php while (have_posts()) : the_post(); ?>
                <?php
                $post_type = get_post_type();
                $type_badge = $post_type === 'series' ? 'Series' : 'Issue';
                $is_standalone = false;

                if ($post_type === 'issue') {
                    $series_id = (int) get_post_meta(get_the_ID(), 'dracka_series_id', true);
                    $is_standalone = $series_id <= 0;
                }
                ?>
                <article <?php post_class('issue-card'); ?>>
                    <div class="card-badges">
                        <span class="content-badge"><?php echo esc_html($type_badge); ?></span>
                        <?php if ($is_standalone) : ?>
                            <span class="content-badge content-badge--muted">Standalone</span>
                        <?php endif; ?>
                    </div>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="library-thumb"><?php the_post_thumbnail('medium'); ?></div>
                    <?php endif; ?>

                    <div class="library-excerpt">
                        <?php the_excerpt(); ?>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p><?php echo esc_html($empty_messages[$active_tab]); ?></p>
    <?php endif; ?>
</main>

<?php
get_footer();
