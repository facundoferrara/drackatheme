<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
  <?php wp_body_open(); ?>

  <header class="site-header">
    <div class="header-inner">
      <button
        class="header-action header-action--info"
        type="button"
        aria-label="Open info panel"
        aria-expanded="false"
        aria-controls="mobile-info-panel"
        data-panel-target="mobile-info-panel">🛈</button>
      <?php
      $dracka_logo_data = dracka_get_active_logo_animation_data();
      $dracka_site_name = get_bloginfo('name');
      ?>
      <div class="logo">
        <?php if (!empty($dracka_logo_data['svg_url'])) : ?>
          <a
            class="dracka-animated-logo js-animated-logo"
            href="<?php echo esc_url(home_url('/')); ?>"
            aria-label="<?php echo esc_attr($dracka_site_name); ?>"
            data-animation-urls="<?php echo esc_attr(wp_json_encode($dracka_logo_data['animation_urls'])); ?>"
            data-interval="5000"
            data-trigger-chance="0.5"
            data-play-duration="2000">
            <span class="dracka-logo-frame" aria-hidden="true">
              <img class="dracka-logo-static" src="<?php echo esc_url($dracka_logo_data['svg_url']); ?>" alt="<?php echo esc_attr($dracka_site_name); ?>">
              <img class="dracka-logo-animation" src="" alt="" aria-hidden="true" hidden>
            </span>
          </a>
        <?php else : ?>
          <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html($dracka_site_name); ?></a>
        <?php endif; ?>
      </div>
      <button
        class="header-action header-action--menu hamburger"
        type="button"
        aria-label="Open menu panel"
        aria-expanded="false"
        aria-controls="mobile-menu-panel"
        data-panel-target="mobile-menu-panel">☰</button>
    </div>

    <?php
    $dracka_mobile_panels = [
      [
        'id' => 'mobile-menu-panel',
        'modifier' => 'mobile-overlay--menu',
        'close_label' => 'Close menu panel',
      ],
      [
        'id' => 'mobile-info-panel',
        'modifier' => 'mobile-overlay--info',
        'close_label' => 'Close info panel',
      ],
    ];
    ?>

    <?php foreach ($dracka_mobile_panels as $dracka_mobile_panel) : ?>
      <div class="mobile-overlay <?php echo esc_attr($dracka_mobile_panel['modifier']); ?>" id="<?php echo esc_attr($dracka_mobile_panel['id']); ?>" data-mobile-panel="<?php echo esc_attr($dracka_mobile_panel['id']); ?>" aria-hidden="true">

        <div class="mobile-overlay-panel" data-overlay-panel>

          <div class="overlay-header">
            <button class="overlay-close" type="button" aria-label="<?php echo esc_attr($dracka_mobile_panel['close_label']); ?>" data-panel-close="<?php echo esc_attr($dracka_mobile_panel['id']); ?>">×</button>
          </div>

          <?php if ('mobile-menu-panel' === $dracka_mobile_panel['id']) : ?>
            <nav class="overlay-nav">
              <?php
              wp_nav_menu([
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'overlay-menu',
              ]);
              ?>
            </nav>

            <nav class="overlay-social">
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
          <?php else : ?>
            <section class="overlay-info" aria-label="Information panel">
              <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
              <p>Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
              <p>Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>
            </section>
          <?php endif; ?>

        </div>

        <button
          class="overlay-dismiss-zone"
          type="button"
          aria-label="<?php echo esc_attr($dracka_mobile_panel['close_label']); ?>"
          data-panel-close="<?php echo esc_attr($dracka_mobile_panel['id']); ?>"></button>

      </div>
    <?php endforeach; ?>

  </header>