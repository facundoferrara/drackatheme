<?php
get_header();

$active_tab = dracka_get_library_tab();

$empty_messages = [
    'series'      => 'No series yet.',
    'issues'      => 'No issues yet.',
    'standalones' => 'No standalones yet.',
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

    <?php if ($active_tab === 'series') : ?>
        <?php
        // Collect all series from the main (paginated) loop.
        $series_cards = [];
        while (have_posts()) {
            the_post();
            $series_cards[] = [
                'id'         => get_the_ID(),
                'permalink'  => get_permalink(),
                'title'      => get_the_title(),
                'has_thumb'  => has_post_thumbnail(),
                'sort_date'  => (int) get_the_date('U'),
                'premiere'   => dracka_get_premiere_badge_markup(get_the_ID(), 10),
            ];
        }

        // Batch-find the latest published issue date for each series on this page.
        $series_ids = wp_list_pluck($series_cards, 'id');
        if (!empty($series_ids)) {
            $issues_q = new WP_Query([
                'post_type'      => 'issue',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'meta_query'     => [[
                    'key'     => 'dracka_series_id',
                    'value'   => $series_ids,
                    'compare' => 'IN',
                    'type'    => 'NUMERIC',
                ]],
            ]);
            $series_latest_date = [];
            foreach ($issues_q->posts as $iss) {
                $sid = (int) get_post_meta($iss->ID, 'dracka_series_id', true);
                if (!isset($series_latest_date[$sid])) {
                    $series_latest_date[$sid] = strtotime($iss->post_date);
                }
            }
            wp_reset_postdata();
            foreach ($series_cards as &$sc) {
                if (isset($series_latest_date[$sc['id']])) {
                    $sc['sort_date'] = $series_latest_date[$sc['id']];
                }
            }
            unset($sc);
        }

        // Sort by most-recent-activity date, newest first.
        $all_cards = $series_cards;
        usort($all_cards, fn($a, $b) => $b['sort_date'] <=> $a['sort_date']);
        ?>

        <?php if (!empty($all_cards)) : ?>
            <div class="<?php echo esc_attr($archive_class); ?>">
                <?php foreach ($all_cards as $card) : ?>
                    <article class="series-card">
                        <?php if ($card['premiere'] !== '') : ?>
                            <div class="card-badges card-badges--ribbon"><?php echo wp_kses_post($card['premiere']); ?></div>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($card['permalink']); ?>" class="series-card__link">
                            <?php if ($card['has_thumb']) : ?>
                                <div class="series-card__thumb"><?php echo get_the_post_thumbnail($card['id'], 'large'); ?></div>
                            <?php endif; ?>
                            <div class="series-card__overlay">
                                <h2 class="series-card__title"><?php echo esc_html($card['title']); ?></h2>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php the_posts_pagination(); ?>
        <?php else : ?>
            <p><?php echo esc_html($empty_messages['series']); ?></p>
        <?php endif; ?>

    <?php elseif ($active_tab === 'standalones') : ?>
        <?php if (have_posts()) : ?>
            <div class="<?php echo esc_attr($archive_class); ?>">
                <?php while (have_posts()) : the_post(); ?>
                    <article class="series-card">
                        <div class="card-badges card-badges--corner"><span class="badge badge--standalone">Standalone</span></div>
                        <a href="<?php the_permalink(); ?>" class="series-card__link">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="series-card__thumb"><?php the_post_thumbnail('large'); ?></div>
                            <?php endif; ?>
                            <div class="series-card__overlay">
                                <h2 class="series-card__title"><?php the_title(); ?></h2>
                            </div>
                        </a>
                    </article>
                <?php endwhile; ?>
            </div>
            <?php the_posts_pagination(); ?>
        <?php else : ?>
            <p><?php echo esc_html($empty_messages['standalones']); ?></p>
        <?php endif; ?>

    <?php elseif (have_posts()) : ?>
        <div class="<?php echo esc_attr($archive_class); ?>">
            <?php while (have_posts()) : the_post(); ?>
                <?php $premiere_badge_markup = dracka_get_premiere_badge_markup(get_the_ID(), 10); ?>
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
            <?php endwhile; ?>
        </div>
        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p><?php echo esc_html($empty_messages[$active_tab]); ?></p>
    <?php endif; ?>
</main>

<?php
get_footer();
