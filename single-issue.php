<?php
get_header();
$series_id = (int) get_post_meta(get_the_ID(), 'dracka_series_id', true);
$series_link = $series_id ? get_permalink($series_id) : '';
$issue_id = get_the_ID();
$access_mode = dracka_get_issue_access_mode($issue_id);
$flipbook_id = (int) get_post_meta($issue_id, DRACKA_ISSUE_FLIPBOOK_ID_META_KEY, true);
$patreon_url = (string) get_post_meta($issue_id, DRACKA_ISSUE_PATREON_URL_META_KEY, true);
$patreon_image_id = (int) get_post_meta($issue_id, DRACKA_ISSUE_PATREON_IMAGE_META_KEY, true);
$issue_navigation = dracka_get_issue_series_navigation($issue_id);
$series_issues = isset($issue_navigation['issues']) && is_array($issue_navigation['issues']) ? $issue_navigation['issues'] : [];
$previous_issue = isset($issue_navigation['previous']) && is_array($issue_navigation['previous']) ? $issue_navigation['previous'] : null;
$next_issue = isset($issue_navigation['next']) && is_array($issue_navigation['next']) ? $issue_navigation['next'] : null;
$last_issue = isset($issue_navigation['last']) && is_array($issue_navigation['last']) ? $issue_navigation['last'] : null;
$first_issue = ($previous_issue !== null && !empty($series_issues)) ? $series_issues[0] : null;
$has_episode_navigation = !empty($series_issues);
?>

<main class="issue-single">
    <?php dracka_render_age_gate(); ?>
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <h1><?php the_title(); ?></h1>

                <?php if ($series_link) : ?>
                    <p class="issue-series">Series: <a href="<?php echo esc_url($series_link); ?>"><?php echo esc_html(get_the_title($series_id)); ?></a></p>
                <?php endif; ?>

                <div class="issue-content">
                    <?php
                    if ($access_mode === 'patreon') {
                        if ($patreon_image_id > 0) {
                            echo '<div class="issue-patreon-image">';
                            if ($patreon_url !== '') {
                                echo '<a href="' . esc_url($patreon_url) . '" target="_blank" rel="noopener noreferrer">';
                                echo wp_get_attachment_image($patreon_image_id, 'large');
                                echo '</a>';
                            } else {
                                echo wp_get_attachment_image($patreon_image_id, 'large');
                            }
                            echo '</div>';
                        }
                    } elseif ($flipbook_id > 0 && shortcode_exists('dflip')) {
                        echo do_shortcode('[dflip id="' . esc_attr((string) $flipbook_id) . '"]');
                    } elseif ($flipbook_id > 0) {
                        echo '<div class="issue-error">';
                        echo '<p><strong>DearFlip Flipbook Plugin Not Available</strong></p>';
                        echo '<p>The DearFlip plugin is required to display this issue as a flipbook. Please ensure the plugin is installed and activated.</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="issue-error">';
                        echo '<p><strong>Flipbook Not Configured</strong></p>';
                        echo '<p>This issue has no selected DearFlip book yet.</p>';
                        echo '</div>';
                    }
                    ?>
                </div>

                <?php if ($has_episode_navigation) : ?>
                    <section class="issue-episodes-nav dracka-collapsible" data-comments-collapsible>
                        <div class="issue-episodes-nav__bar dracka-collapsible__toggle" role="button" tabindex="0" aria-expanded="false" aria-label="Toggle episodes list">
                            <?php if ($first_issue) : ?>
                                <a class="issue-episodes-nav__segment" data-collapsible-ignore-toggle href="<?php echo esc_url((string) $first_issue['url']); ?>">
                                    <span class="issue-episodes-nav__arrow issue-episodes-nav__arrow--left" aria-hidden="true"></span>
                                    <span class="issue-episodes-nav__arrow issue-episodes-nav__arrow--left" aria-hidden="true"></span>
                                    <span>First</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($previous_issue) : ?>
                                <a class="issue-episodes-nav__segment" data-collapsible-ignore-toggle href="<?php echo esc_url((string) $previous_issue['url']); ?>">
                                    <span class="issue-episodes-nav__arrow issue-episodes-nav__arrow--left" aria-hidden="true"></span>
                                    <span>Previous</span>
                                </a>
                            <?php endif; ?>

                            <span class="issue-episodes-nav__segment issue-episodes-nav__segment--episodes">
                                <span class="dracka-collapsible__title">Episodes</span>
                                <span class="dracka-collapsible__arrow" aria-hidden="true"></span>
                            </span>

                            <?php if ($next_issue) : ?>
                                <a class="issue-episodes-nav__segment" data-collapsible-ignore-toggle href="<?php echo esc_url((string) $next_issue['url']); ?>">
                                    <span>Next</span>
                                    <span class="issue-episodes-nav__arrow issue-episodes-nav__arrow--right" aria-hidden="true"></span>
                                </a>
                            <?php endif; ?>

                            <?php if ($last_issue) : ?>
                                <a class="issue-episodes-nav__segment" data-collapsible-ignore-toggle href="<?php echo esc_url((string) $last_issue['url']); ?>">
                                    <span>Last</span>
                                    <span class="issue-episodes-nav__arrow issue-episodes-nav__arrow--right" aria-hidden="true"></span>
                                    <span class="issue-episodes-nav__arrow issue-episodes-nav__arrow--right" aria-hidden="true"></span>
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="dracka-collapsible__content issue-episodes-nav__content" hidden>
                            <div class="series-issues-list">
                                <?php foreach ($series_issues as $series_issue) : ?>
                                    <?php
                                    $series_issue_id = isset($series_issue['id']) ? (int) $series_issue['id'] : 0;
                                    if ($series_issue_id <= 0) {
                                        continue;
                                    }

                                    $series_issue_title = isset($series_issue['title']) ? (string) $series_issue['title'] : get_the_title($series_issue_id);
                                    $series_issue_url = isset($series_issue['url']) ? (string) $series_issue['url'] : get_permalink($series_issue_id);
                                    $series_issue_date = isset($series_issue['date']) ? (string) $series_issue['date'] : get_the_date('', $series_issue_id);
                                    $is_current_issue = $series_issue_id === $issue_id;
                                    $issue_premiere_badge_markup = dracka_get_premiere_badge_markup($series_issue_id, 10);
                                    ?>
                                    <article class="series-issue-row<?php echo $is_current_issue ? ' is-current' : ''; ?>">
                                        <div class="series-issue-row__media">
                                            <?php if ($issue_premiere_badge_markup !== '') : ?>
                                                <div class="card-badges card-badges--ribbon"><?php echo wp_kses_post($issue_premiere_badge_markup); ?></div>
                                            <?php endif; ?>
                                            <?php if (has_post_thumbnail($series_issue_id)) : ?>
                                                <?php if ($is_current_issue) : ?>
                                                    <span class="series-issue-row__thumb-link" aria-hidden="true">
                                                        <?php echo get_the_post_thumbnail($series_issue_id, 'medium'); ?>
                                                    </span>
                                                <?php else : ?>
                                                    <a href="<?php echo esc_url($series_issue_url); ?>" class="series-issue-row__thumb-link" aria-label="<?php echo esc_attr($series_issue_title); ?>">
                                                        <?php echo get_the_post_thumbnail($series_issue_id, 'medium'); ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <div class="series-issue-row__thumb-placeholder" aria-hidden="true"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="series-issue-row__content">
                                            <div class="series-issue-row__title-wrap">
                                                <h3>
                                                    <?php if ($is_current_issue) : ?>
                                                        <span><?php echo esc_html($series_issue_title); ?></span>
                                                    <?php else : ?>
                                                        <a href="<?php echo esc_url($series_issue_url); ?>"><?php echo esc_html($series_issue_title); ?></a>
                                                    <?php endif; ?>
                                                </h3>
                                                <?php if ($is_current_issue) : ?>
                                                    <span class="issue-episodes-nav__current-pill">Current</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="series-issue-row__date"><?php echo esc_html($series_issue_date); ?></p>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php get_template_part('template-parts/comments-box', null, ['initially_open' => false]); ?>

<?php
get_footer();
