/**
 * Artwork single-page navigation.
 *
 * - Prev/next arrow buttons on desktop
 * - Left/right arrow keys (skipped when typing)
 * - Touch swipe on the image frame (mobile)
 * - Animated slide + fade transition
 * - URL updated via history.pushState()
 * - Adjacent images preloaded
 * - Browser back/forward (popstate) supported
 */
(function () {
  'use strict';

  // ---------------------------------------------------------------------------
  // State
  // ---------------------------------------------------------------------------

  /** @type {{ restBase: string, current: ArtworkItem, previous: ArtworkItem|null, next: ArtworkItem|null }|null} */
  var navData = null;

  /** @type {Map<number, { current: ArtworkItem, previous: ArtworkItem|null, next: ArtworkItem|null }>} */
  var cache = new Map();

  var busy = false;

  // ---------------------------------------------------------------------------
  // Types (JSDoc only, no runtime cost)
  // ---------------------------------------------------------------------------
  /**
   * @typedef {{ id: number, url: string, title: string, image: { src: string, srcset: string, sizes: string, alt: string } }} ArtworkItem
   */

  // ---------------------------------------------------------------------------
  // Init
  // ---------------------------------------------------------------------------

  function init() {
    var dataEl = document.getElementById('artwork-nav-data');
    if (!dataEl) return;

    try {
      navData = JSON.parse(dataEl.textContent);
    } catch (_) {
      return;
    }

    if (!navData || !navData.current) return;

    var container = document.querySelector('.artwork-nav');
    if (!container) return;

    // Seed initial state so popstate can navigate back to the opening artwork.
    history.replaceState(
      { artworkId: navData.current.id, navData: { previous: navData.previous, next: navData.next } },
      document.title,
      location.href
    );

    // Cache current nav entry.
    cache.set(navData.current.id, { current: navData.current, previous: navData.previous, next: navData.next });

    // Preload adjacent images immediately.
    preloadItem(navData.previous);
    preloadItem(navData.next);

    // Arrow buttons.
    var prevBtn = container.querySelector('.artwork-nav__arrow--prev');
    var nextBtn = container.querySelector('.artwork-nav__arrow--next');

    if (prevBtn) {
      prevBtn.addEventListener('click', function () { navigate('prev'); });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () { navigate('next'); });
    }

    // Keyboard.
    document.addEventListener('keydown', onKeyDown);

    // Touch / swipe.
    var frame = container.querySelector('.artwork-nav__frame');
    if (frame) setupSwipe(frame);

    // Browser back/forward.
    window.addEventListener('popstate', onPopState);
  }

  // ---------------------------------------------------------------------------
  // Navigation
  // ---------------------------------------------------------------------------

  /**
   * Navigate in the given direction using the current navData.
   * @param {'prev'|'next'} direction
   */
  function navigate(direction) {
    if (busy) return;
    if (!navData) return;

    var target = direction === 'prev' ? navData.previous : navData.next;
    if (!target) return;

    performTransition(target, direction, true);
  }

  /**
   * Transition to a target artwork item.
   *
   * @param {ArtworkItem}     target        The artwork to transition to.
   * @param {'prev'|'next'}   direction     Visual direction of the transition.
   * @param {boolean}         pushHistory   Whether to call history.pushState().
   */
  function performTransition(target, direction, pushHistory) {
    if (busy) return;
    busy = true;

    var container = document.querySelector('.artwork-nav');
    var frame     = container ? container.querySelector('.artwork-nav__frame') : null;
    var img       = frame ? frame.querySelector('img.artwork-nav__img') : null;

    if (!frame || !img) {
      busy = false;
      return;
    }

    // 1. Start exit animation.
    var exitClass   = direction === 'next' ? 'is-exiting-left' : 'is-exiting-right';
    var enterClass  = direction === 'next' ? 'is-entering-from-right' : 'is-entering-from-left';

    frame.classList.add(exitClass);

    img.addEventListener('transitionend', function onExit(e) {
      if (e.propertyName !== 'opacity') return;
      img.removeEventListener('transitionend', onExit);
      frame.classList.remove(exitClass);

      // 2. Swap image content (instant, no transition).
      var newImg = new Image();
      newImg.className = 'artwork-nav__img';
      newImg.src       = target.image.src;
      newImg.alt       = target.image.alt || target.title;
      if (target.image.srcset) {
        newImg.srcset = target.image.srcset;
        newImg.sizes  = target.image.sizes || '100vw';
      }
      newImg.setAttribute('loading', 'eager');
      newImg.draggable = false;

      // Apply enter-start class BEFORE adding to DOM so there's no transition flash.
      frame.classList.add(enterClass);
      img.replaceWith(newImg);

      // 3. Trigger reflow so the browser registers the starting state.
      void frame.offsetWidth; // eslint-disable-line no-void

      // 4. Remove the no-transition start class → CSS transition fires from offset to 0.
      frame.classList.remove(enterClass);

      // 5. When enter animation ends, update the rest of the page state.
      newImg.addEventListener('transitionend', function onEnter(e2) {
        if (e2.propertyName !== 'opacity') return;
        newImg.removeEventListener('transitionend', onEnter);
        finishTransition(target, pushHistory);
      });

      // Fallback in case transitionend doesn't fire (e.g. prefers-reduced-motion).
      setTimeout(function () { finishTransition(target, pushHistory); }, 450);
    });

    // Fallback for exit.
    setTimeout(function () {
      if (!busy) return;
      frame.classList.remove(exitClass);
      var newImg = frame.querySelector('img.artwork-nav__img');
      if (newImg) {
        newImg.src    = target.image.src;
        newImg.alt    = target.image.alt || target.title;
        if (target.image.srcset) newImg.srcset = target.image.srcset;
      }
      finishTransition(target, pushHistory);
    }, 500);
  }

  /**
   * Called once the transition animation has fully completed.
   * Updates navData, arrow states, URL, page title, then fetches next nav data.
   *
   * @param {ArtworkItem} target
   * @param {boolean}     pushHistory
   */
  function finishTransition(target, pushHistory) {
    // Idempotent guard: only run once even if both transitionend and setTimeout fire.
    if (!busy) return;
    busy = false;

    var cachedEntry = cache.get(target.id);
    if (cachedEntry) {
      applyNavData(target, cachedEntry.previous, cachedEntry.next, pushHistory);
    } else {
      // Optimistically apply with no prev/next while fetching.
      applyNavData(target, null, null, pushHistory);
      fetchNavData(target.id, function (fetched) {
        if (!fetched) return;
        cache.set(target.id, fetched);
        // Only update prev/next if we're still on this artwork.
        if (navData && navData.current.id === target.id) {
          navData.previous = fetched.previous;
          navData.next     = fetched.next;
          updateArrows();
          preloadItem(fetched.previous);
          preloadItem(fetched.next);
        }
      });
    }
  }

  /**
   * Applies the new navData and updates all page-level state.
   *
   * @param {ArtworkItem}         current
   * @param {ArtworkItem|null}    previous
   * @param {ArtworkItem|null}    next
   * @param {boolean}             pushHistory
   */
  function applyNavData(current, previous, next, pushHistory) {
    navData = { restBase: navData ? navData.restBase : '', current: current, previous: previous, next: next };

    updateArrows();
    preloadItem(previous);
    preloadItem(next);

    // Update title.
    var h1 = document.querySelector('.artwork-single__title');
    if (h1) h1.textContent = current.title;
    document.title = current.title;

    // Update URL.
    if (pushHistory) {
      history.pushState(
        { artworkId: current.id, navData: { previous: previous, next: next } },
        current.title,
        current.url
      );
    }
  }

  /**
   * Sync prev/next button disabled states from current navData.
   */
  function updateArrows() {
    var container = document.querySelector('.artwork-nav');
    if (!container || !navData) return;
    var prevBtn = container.querySelector('.artwork-nav__arrow--prev');
    var nextBtn = container.querySelector('.artwork-nav__arrow--next');
    setButtonState(prevBtn, !!navData.previous);
    setButtonState(nextBtn, !!navData.next);
  }

  /**
   * @param {HTMLButtonElement|null} btn
   * @param {boolean} enabled
   */
  function setButtonState(btn, enabled) {
    if (!btn) return;
    btn.disabled = !enabled;
    btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
  }

  // ---------------------------------------------------------------------------
  // Keyboard
  // ---------------------------------------------------------------------------

  function onKeyDown(e) {
    // Skip when focus is inside a text field.
    var tag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || document.activeElement.isContentEditable) {
      return;
    }
    if (e.key === 'ArrowLeft')  navigate('prev');
    if (e.key === 'ArrowRight') navigate('next');
  }

  // ---------------------------------------------------------------------------
  // Touch / swipe
  // ---------------------------------------------------------------------------

  /**
   * @param {HTMLElement} frame
   */
  function setupSwipe(frame) {
    var startX = 0;
    var startY = 0;
    var deltaX = 0;
    var intentLocked = false; // true once we've decided horizontal vs vertical

    frame.addEventListener('touchstart', function (e) {
      startX      = e.touches[0].clientX;
      startY      = e.touches[0].clientY;
      deltaX      = 0;
      intentLocked = false;
    }, { passive: true });

    frame.addEventListener('touchmove', function (e) {
      deltaX = e.touches[0].clientX - startX;
      var deltaY = e.touches[0].clientY - startY;

      if (!intentLocked) {
        if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 8) {
          intentLocked = true;
        } else if (Math.abs(deltaY) > Math.abs(deltaX) && Math.abs(deltaY) > 8) {
          intentLocked = true;
          deltaX = 0; // vertical – cancel swipe
        }
      }

      if (intentLocked && Math.abs(deltaX) > 0) {
        e.preventDefault();
      }
    }, { passive: false });

    frame.addEventListener('touchend', function () {
      var threshold = 50;
      if (Math.abs(deltaX) >= threshold) {
        navigate(deltaX > 0 ? 'prev' : 'next');
      }
      deltaX = 0;
      intentLocked = false;
    }, { passive: true });
  }

  // ---------------------------------------------------------------------------
  // Browser back/forward
  // ---------------------------------------------------------------------------

  function onPopState(e) {
    if (!e.state || !e.state.artworkId) return;
    var id = e.state.artworkId;

    var cachedEntry = cache.get(id);
    var previous    = cachedEntry ? cachedEntry.previous : (e.state.navData ? e.state.navData.previous : null);
    var next        = cachedEntry ? cachedEntry.next     : (e.state.navData ? e.state.navData.next     : null);

    // Determine direction based on whether we're going to a previous artwork.
    var direction = (navData && navData.previous && navData.previous.id === id) ? 'prev' : 'next';

    var target = cachedEntry ? cachedEntry.current : null;
    if (!target && navData) {
      if (navData.previous && navData.previous.id === id) target = navData.previous;
      else if (navData.next && navData.next.id === id)    target = navData.next;
    }

    if (!target) {
      // Fall back to full page load.
      location.href = e.state.artworkUrl || location.href;
      return;
    }

    // Merge in prev/next if available.
    if (previous !== undefined) target = Object.assign({}, target);

    performTransition(target, direction, false);

    // Restore prev/next after transition.
    if (cachedEntry) {
      navData = { restBase: navData ? navData.restBase : '', current: target, previous: previous, next: next };
    }
  }

  // ---------------------------------------------------------------------------
  // REST fetch
  // ---------------------------------------------------------------------------

  /**
   * Fetches artwork navigation data from the REST endpoint.
   *
   * @param {number}   artworkId
   * @param {function} callback - Called with { current, previous, next } or null on failure.
   */
  function fetchNavData(artworkId, callback) {
    if (!navData || !navData.restBase) return;
    var url = navData.restBase + artworkId;

    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.setRequestHeader('X-WP-Nonce', ''); // public endpoint – no nonce needed
    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          var data = JSON.parse(xhr.responseText);
          callback({ current: data.current, previous: data.previous, next: data.next });
        } catch (_) {
          callback(null);
        }
      } else {
        callback(null);
      }
    };
    xhr.onerror = function () { callback(null); };
    xhr.send();
  }

  // ---------------------------------------------------------------------------
  // Preloading
  // ---------------------------------------------------------------------------

  /**
   * Preloads a navigation target's image into the browser cache via a hidden Image object.
   * @param {ArtworkItem|null} item
   */
  function preloadItem(item) {
    if (!item || !item.image || !item.image.src) return;
    var img = new Image();
    if (item.image.srcset) {
      img.srcset = item.image.srcset;
      img.sizes  = item.image.sizes || '100vw';
    }
    img.src = item.image.src;
  }

  // ---------------------------------------------------------------------------
  // Bootstrap
  // ---------------------------------------------------------------------------

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
