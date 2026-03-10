/**
 * Initializes mobile overlay panels, including button toggles, swipe-close, and scroll locking.
 *
 * The swipe gesture intentionally requires strong upward travel to avoid accidental closes
 * while users scroll inside the panel.
 */
function setupMobilePanels() {
  const panelButtons = document.querySelectorAll('[data-panel-target]');
  const closeButtons = document.querySelectorAll('[data-panel-close]');
  const panelElements = document.querySelectorAll('[data-mobile-panel]');

  if (!panelButtons.length || !panelElements.length) {
    return;
  }

  const getPanelById = (panelId) => document.querySelector(`[data-mobile-panel="${panelId}"]`);

  const setupSwipeClose = (panelElement, panelId) => {
    const swipeElement = panelElement.querySelector('[data-overlay-panel]');

    if (!swipeElement) {
      return;
    }

    let startX = 0;
    let startY = 0;
    let hasTouch = false;
    let shouldCloseBySwipe = false;
    let suppressClick = false;

    swipeElement.addEventListener('touchstart', (event) => {
      if (!event.touches || event.touches.length === 0) {
        return;
      }

      const firstTouch = event.touches[0];
      startX = firstTouch.clientX;
      startY = firstTouch.clientY;
      hasTouch = true;
      shouldCloseBySwipe = false;
    }, { passive: true, capture: true });

    swipeElement.addEventListener('touchmove', (event) => {
      if (!hasTouch || !event.touches || event.touches.length === 0) {
        return;
      }

      const firstTouch = event.touches[0];
      const deltaX = firstTouch.clientX - startX;
      const deltaY = firstTouch.clientY - startY;

      if (deltaY < -60 && Math.abs(deltaY) > Math.abs(deltaX) * 1.2) {
        shouldCloseBySwipe = true;
      }
    }, { passive: true, capture: true });

    swipeElement.addEventListener('touchend', () => {
      if (!hasTouch) {
        return;
      }

      hasTouch = false;

      if (shouldCloseBySwipe) {
        shouldCloseBySwipe = false;
        suppressClick = true;
        closePanel(panelId);
      }
    }, { passive: true, capture: true });

    swipeElement.addEventListener('click', (event) => {
      if (!suppressClick) {
        return;
      }

      suppressClick = false;
      event.preventDefault();
      event.stopPropagation();
    }, true);
  };

  const updateScrollLock = () => {
    const hasOpenPanel = Array.from(panelElements).some((panelElement) => panelElement.classList.contains('is-open'));
    document.body.classList.toggle('no-scroll', hasOpenPanel);
  };

  const setButtonExpanded = (panelId, isExpanded) => {
    panelButtons.forEach((buttonElement) => {
      if (buttonElement.getAttribute('data-panel-target') === panelId) {
        buttonElement.setAttribute('aria-expanded', String(isExpanded));
      }
    });
  };

  const closePanel = (panelId) => {
    const panelElement = getPanelById(panelId);

    if (!panelElement) {
      return;
    }

    panelElement.classList.remove('is-open');
    panelElement.setAttribute('aria-hidden', 'true');
    setButtonExpanded(panelId, false);
    updateScrollLock();
  };

  const closeAllPanels = () => {
    panelElements.forEach((panelElement) => {
      const panelId = panelElement.getAttribute('data-mobile-panel');
      panelElement.classList.remove('is-open');
      panelElement.setAttribute('aria-hidden', 'true');

      if (panelId) {
        setButtonExpanded(panelId, false);
      }
    });

    updateScrollLock();
  };

  const openPanel = (panelId) => {
    const panelElement = getPanelById(panelId);

    if (!panelElement) {
      return;
    }

    closeAllPanels();
    panelElement.classList.add('is-open');
    panelElement.setAttribute('aria-hidden', 'false');
    setButtonExpanded(panelId, true);
    updateScrollLock();
  };

  panelButtons.forEach((buttonElement) => {
    buttonElement.addEventListener('click', () => {
      const panelId = buttonElement.getAttribute('data-panel-target');

      if (!panelId) {
        return;
      }

      const panelElement = getPanelById(panelId);
      const isOpen = panelElement ? panelElement.classList.contains('is-open') : false;

      if (isOpen) {
        closePanel(panelId);
      } else {
        openPanel(panelId);
      }
    });
  });

  closeButtons.forEach((buttonElement) => {
    buttonElement.addEventListener('click', () => {
      const panelId = buttonElement.getAttribute('data-panel-close');

      if (!panelId) {
        return;
      }

      closePanel(panelId);
    });
  });

  panelElements.forEach((panelElement) => {
    const panelId = panelElement.getAttribute('data-mobile-panel');

    if (!panelId) {
      return;
    }

    setupSwipeClose(panelElement, panelId);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeAllPanels();
    }
  });
}

/**
 * Sets up probabilistic logo animation playback in the header.
 *
 * Behavior summary:
 * - Uses a polling interval and trigger chance to keep animation occasional.
 * - Skips playback while the tab is hidden or reduced-motion is enabled.
 * - Reuses original file URLs so browser caching can prevent redundant downloads.
 */
function setupAnimatedLogo() {
  const logoElement = document.querySelector('.js-animated-logo');

  if (!logoElement) {
    return;
  }

  const staticImage = logoElement.querySelector('.dracka-logo-static');
  const animationImage = logoElement.querySelector('.dracka-logo-animation');

  if (!staticImage || !animationImage) {
    return;
  }

  let animationUrls = [];

  try {
    const parsedUrls = JSON.parse(logoElement.dataset.animationUrls || '[]');
    if (Array.isArray(parsedUrls)) {
      animationUrls = parsedUrls.filter((url) => typeof url === 'string' && url.length > 0);
    }
  } catch (error) {
    animationUrls = [];
  }

  if (animationUrls.length === 0) {
    return;
  }

  const reduceMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

  if (reduceMotionQuery.matches) {
    return;
  }

  const intervalMs = Number.parseInt(logoElement.dataset.interval || '5000', 10);
  const triggerChance = Number.parseFloat(logoElement.dataset.triggerChance || '0.5');
  const playDurationMs = Number.parseInt(logoElement.dataset.playDuration || '2000', 10);

  let isPlaying = false;
  let lastAnimationUrl = '';
  let activeTimeoutId = null;

  const stopAnimation = () => {
    if (activeTimeoutId) {
      window.clearTimeout(activeTimeoutId);
      activeTimeoutId = null;
    }

    animationImage.onload = null;
    animationImage.onerror = null;

    animationImage.hidden = true;
    animationImage.removeAttribute('src');
    staticImage.hidden = false;
    isPlaying = false;
  };

  const getRandomAnimationUrl = () => {
    const pool = animationUrls.length > 1
      ? animationUrls.filter((url) => url !== lastAnimationUrl)
      : animationUrls;

    if (pool.length === 0) {
      return '';
    }

    return pool[Math.floor(Math.random() * pool.length)] || '';
  };

  const maybePlayAnimation = () => {
    if (document.hidden || isPlaying) {
      return;
    }

    if (Math.random() >= triggerChance) {
      return;
    }

    const selectedUrl = getRandomAnimationUrl();

    if (!selectedUrl) {
      return;
    }

    isPlaying = true;
    lastAnimationUrl = selectedUrl;

    animationImage.onload = () => {
      if (!isPlaying) {
        return;
      }

      staticImage.hidden = true;
      animationImage.hidden = false;

      activeTimeoutId = window.setTimeout(() => {
        stopAnimation();
      }, Math.max(playDurationMs, 500));
    };

    animationImage.onerror = () => {
      stopAnimation();
    };

    // Keep native browser caching for animation files instead of forcing a unique URL each cycle.
    animationImage.src = selectedUrl;
  };

  window.setInterval(maybePlayAnimation, Math.max(intervalMs, 1000));

  document.addEventListener('visibilitychange', () => {
    if (document.hidden && isPlaying) {
      stopAnimation();
    }
  });
}

/**
 * Wires a collapsible content block with animated expand/collapse transitions.
 *
 * This keeps max-height inline styles in sync with CSS transitions and updates
 * aria-expanded so assistive technology receives the current open state.
 *
 * @param {HTMLElement} blockElement Collapsible block root element.
 */
function setupCollapsibleBlock(blockElement) {
  const toggleButton = blockElement.querySelector('.dracka-collapsible__toggle');
  const content = blockElement.querySelector('.dracka-collapsible__content');

  if (!toggleButton || !content) {
    return;
  }

  let isTransitioning = false;

  const finishExpand = (event) => {
    if (event.propertyName !== 'max-height') {
      return;
    }

    if (toggleButton.getAttribute('aria-expanded') !== 'true') {
      return;
    }

    content.style.maxHeight = 'none';
    isTransitioning = false;
    content.removeEventListener('transitionend', finishExpand);
  };

  const finishCollapse = (event) => {
    if (event.propertyName !== 'max-height') {
      return;
    }

    if (toggleButton.getAttribute('aria-expanded') !== 'false') {
      return;
    }

    content.hidden = true;
    content.style.maxHeight = '';
    isTransitioning = false;
    content.removeEventListener('transitionend', finishCollapse);
  };

  const expandContent = () => {
    content.hidden = false;
    content.style.maxHeight = '0px';

    requestAnimationFrame(() => {
      content.classList.add('is-open');
      content.style.maxHeight = `${content.scrollHeight}px`;
      content.addEventListener('transitionend', finishExpand);
    });
  };

  const collapseContent = () => {
    if (content.style.maxHeight === 'none') {
      content.style.maxHeight = `${content.scrollHeight}px`;
    }

    requestAnimationFrame(() => {
      content.classList.remove('is-open');
      content.style.maxHeight = '0px';
      content.addEventListener('transitionend', finishCollapse);
    });
  };

  toggleButton.addEventListener('click', () => {
    if (isTransitioning) {
      return;
    }

    const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';
    const nextState = !isExpanded;

    isTransitioning = true;
    toggleButton.setAttribute('aria-expanded', String(nextState));

    if (nextState) {
      expandContent();
    } else {
      collapseContent();
    }
  });
}

/**
 * Verifies a block has at least one renderable card before interaction logic is attached.
 *
 * @param {HTMLElement} blockElement Block root element.
 * @returns {boolean}
 */
function blockHasRenderableCards(blockElement) {
  const grid = blockElement.querySelector('[data-content-grid]');

  if (!grid) {
    return false;
  }

  return Boolean(
    grid.querySelector('.dracka-issues-card, .dracka-artwork-card')
  );
}

/**
 * Binds "show more" pagination behavior for latest-content blocks.
 *
 * The loader preserves server-generated card markup and progressively appends
 * it while tracking offset/cap state in data attributes.
 *
 * @param {HTMLElement} blockElement Block root element.
 */
function setupLatestContentLoader(blockElement) {
  const showMoreButton = blockElement.querySelector('[data-show-more]');
  const grid = blockElement.querySelector('[data-content-grid]');
  const loadUrl = blockElement.dataset.loadUrl;
  const increment = Number.parseInt(blockElement.dataset.increment || '8', 10);
  const maxItemsCap = Number.parseInt(blockElement.dataset.maxItemsCap || '0', 10);
  const sortMode = blockElement.dataset.sortMode || 'newest';
  const showMoreLabel = blockElement.dataset.showMoreLabel || 'Show more';
  const loadingLabel = blockElement.dataset.loadingLabel || 'Loading...';
  const goLibraryLabel = blockElement.dataset.goLibraryLabel || 'Go to library';
  const goLibraryUrl = blockElement.dataset.goLibraryUrl || '/library/issues/';

  if (!showMoreButton || !grid || !loadUrl) {
    return;
  }

  let nextOffset = Number.parseInt(blockElement.dataset.nextOffset || '0', 10);
  let isLoading = false;

  function replaceWithLibraryLink() {
    const linkElement = document.createElement('a');
    const isArtwork = blockElement.classList.contains('dracka-latest-artwork-block');
    linkElement.className = isArtwork ? 'dracka-artwork-go-library' : 'dracka-issues-go-library';
    linkElement.href = goLibraryUrl;
    linkElement.textContent = goLibraryLabel;
    showMoreButton.replaceWith(linkElement);
  }

  showMoreButton.addEventListener('click', async () => {
    if (isLoading) {
      return;
    }

    isLoading = true;
    showMoreButton.disabled = true;
    showMoreButton.textContent = loadingLabel;

    try {
      const params = new URLSearchParams({
        offset: String(nextOffset),
        limit: String(increment),
        sort: sortMode,
      });

      if (maxItemsCap > 0) {
        params.set('max', String(maxItemsCap));
      }

      const requestUrl = `${loadUrl}?${params.toString()}`;
      const response = await fetch(requestUrl, {
        method: 'GET',
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error('Failed request');
      }

      const payload = await response.json();

      if (payload.items_html) {
        grid.insertAdjacentHTML('beforeend', payload.items_html);
      }

      if (typeof payload.next_offset === 'number') {
        nextOffset = payload.next_offset;
        blockElement.dataset.nextOffset = String(nextOffset);
      }

      if (!payload.has_more) {
        if (payload.reached_cap) {
          replaceWithLibraryLink();
        } else {
          showMoreButton.remove();
        }
      } else {
        showMoreButton.disabled = false;
        showMoreButton.textContent = showMoreLabel;
      }
    } catch (error) {
      showMoreButton.disabled = false;
      showMoreButton.textContent = showMoreLabel;
    } finally {
      isLoading = false;
    }
  });
}

const collapsibleBlocks = document.querySelectorAll('[data-collapsible]');

setupMobilePanels();
setupAnimatedLogo();

collapsibleBlocks.forEach((blockElement) => {
  if (!blockHasRenderableCards(blockElement)) {
    blockElement.remove();
    return;
  }

  setupCollapsibleBlock(blockElement);
  setupLatestContentLoader(blockElement);
});
