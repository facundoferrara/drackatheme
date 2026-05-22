<?php

/**
 * Template Name: Blog
 */

get_header();

$paged = max(1, (int) get_query_var('paged'));

$blog_query = new WP_Query([
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => get_option('posts_per_page'),
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);
?>

<main class="blog-archive">
    <h1>Blog</h1>

    <?php if ($blog_query->have_posts()) : ?>
        <div class="blog-grid">
            <?php while ($blog_query->have_posts()) : $blog_query->the_post(); ?>
                <article <?php post_class('post-card'); ?>>
                    <a href="<?php the_permalink(); ?>" class="post-card__link">
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="post-card__thumb"><?php the_post_thumbnail('large'); ?></div>
                        <?php endif; ?>
                        <div class="post-card__overlay">
                            <h2 class="post-card__title"><?php the_title(); ?></h2>
                            <p class="post-card__meta">
                                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
                                <span class="post-card__author"><?php echo esc_html(get_the_author()); ?></span>
                            </p>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
            <?php wp_reset_postdata(); ?>
        </div>
        <?php
        echo paginate_links([
            'total'   => $blog_query->max_num_pages,
            'current' => $paged,
        ]);
        ?>
    <?php else : ?>
        <p>No posts yet.</p>
    <?php endif; ?>
</main>

<?php
get_footer();
