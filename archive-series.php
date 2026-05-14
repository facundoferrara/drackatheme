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

    <?php get_template_part('template-parts/archive-tabs', null, [
        'tabs'       => [
            'series'      => 'Series',
            'issues'      => 'Issues',
            'standalones' => 'Standalones',
        ],
        'base_url'   => '/library/',
        'active_tab' => $active_tab,
        'nav_label'  => 'Library sections',
    ]); ?>

    <?php if (false) : // Search form — commented out; activate when search is implemented 
    ?>
        <div class="library-search is-hidden">
            <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" aria-label="Library search">
                <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Search the library">
                <input type="hidden" name="dracka_scope" value="library">
                <button type="submit">Search</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (have_posts()) : ?>
        <div class="<?php echo esc_attr($archive_class); ?>">
            <?php while (have_posts()) : the_post(); ?>
                <?php
                $post_type = get_post_type();
                $type_badge = $post_type === 'series' ? 'Series' : 'Issue';
                $is_standalone = false;
                $premiere_badge_markup = dracka_get_premiere_badge_markup(get_the_ID(), 10);

                if ($post_type === 'issue') {
                    $series_id = (int) get_post_meta(get_the_ID(), 'dracka_series_id', true);
                    $is_standalone = $series_id <= 0;
                }
                ?>
                <?php if ($post_type === 'series') : ?>
                    <article <?php post_class('series-card'); ?>>
                        <?php if ($premiere_badge_markup !== '') : ?>
                            <div class="card-badges card-badges--ribbon"><?php echo wp_kses_post($premiere_badge_markup); ?></div>
                        <?php endif; ?>
                        <a href="<?php the_permalink(); ?>" class="series-card__link">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="series-card__thumb"><?php the_post_thumbnail('large'); ?></div>
                            <?php endif; ?>
                            <div class="series-card__overlay">
                                <h2 class="series-card__title"><?php the_title(); ?></h2>
                            </div>
                        </a>
                    </article>
                <?php else : ?>
                    <article <?php post_class('issue-card'); ?>>
                        <?php if ($premiere_badge_markup !== '') : ?>
                            <div class="card-badges card-badges--ribbon"><?php echo wp_kses_post($premiere_badge_markup); ?></div>
                        <?php endif; ?>
                        <a href="<?php the_permalink(); ?>" class="issue-card__link">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="issue-card__thumb"><?php the_post_thumbnail('large'); ?></div>
                            <?php endif; ?>
                            <div class="issue-card__overlay">
                                <h2 class="issue-card__title"><?php the_title(); ?></h2>
                            </div>
                        </a>
                    </article>
                <?php endif; ?>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p><?php echo esc_html($empty_messages[$active_tab]); ?></p>
    <?php endif; ?>
</main>

<?php
get_footer();
