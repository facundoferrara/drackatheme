(function (blocks, element, i18n, blockEditor, components) {
  // Editor-side block registration for a server-rendered latest-artwork block.
  // Frontend markup and incremental loading are handled in PHP + main.js.
  const el = element.createElement;
  const __ = i18n.__;
  const InspectorControls = blockEditor.InspectorControls;
  const PanelBody = components.PanelBody;
  const TextControl = components.TextControl;
  const RangeControl = components.RangeControl;
  const SelectControl = components.SelectControl;

  blocks.registerBlockType('dracka/latest-artwork', {
    apiVersion: 2,
    title: __('Latest Artwork', 'dracka'),
    description: __('Collapsible latest artwork grid with incremental loading.', 'dracka'),
    icon: 'format-gallery',
    category: 'widgets',
    supports: {
      html: false,
    },
    attributes: {
      title: {
        type: 'string',
        default: 'Latest Artwork',
      },
      initialCount: {
        type: 'number',
        default: 8,
      },
      increment: {
        type: 'number',
        default: 8,
      },
      showMoreLabel: {
        type: 'string',
        default: 'Show more',
      },
      maxItemsCap: {
        type: 'number',
        default: 0,
      },
      sortMode: {
        type: 'string',
        default: 'newest',
      },
      goToLibraryLabel: {
        type: 'string',
        default: 'Go to gallery',
      },
      goToLibraryUrl: {
        type: 'string',
        default: '/gallery/artwork/',
      },
    },
    edit: function (props) {
      // Inspector controls define loading strategy and CTA text used on the frontend.
      const attributes = props.attributes;
      const setAttributes = props.setAttributes;

      return el(
        element.Fragment,
        null,
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            {
              title: __('Latest Artwork Settings', 'dracka'),
              initialOpen: true,
            },
            el(TextControl, {
              label: __('Bar title', 'dracka'),
              help: __('Text shown in the collapsed horizontal bar header.', 'dracka'),
              value: attributes.title || '',
              onChange: function (value) {
                setAttributes({ title: value });
              },
            }),
            el(RangeControl, {
              label: __('Initial artwork visible', 'dracka'),
              help: __('How many latest artwork pieces are rendered on first open.', 'dracka'),
              value: attributes.initialCount || 8,
              min: 1,
              max: 24,
              onChange: function (value) {
                setAttributes({ initialCount: Number(value) || 8 });
              },
            }),
            el(RangeControl, {
              label: __('Load more amount', 'dracka'),
              help: __('How many additional artwork pieces are fetched on each Show more click.', 'dracka'),
              value: attributes.increment || 8,
              min: 1,
              max: 24,
              onChange: function (value) {
                setAttributes({ increment: Number(value) || 8 });
              },
            }),
            el(RangeControl, {
              label: __('Maximum items shown in block', 'dracka'),
              help: __('Set to 0 for unlimited. If there are more items than this cap, Show more turns into Go to gallery.', 'dracka'),
              value: attributes.maxItemsCap || 0,
              min: 0,
              max: 200,
              onChange: function (value) {
                setAttributes({ maxItemsCap: Number(value) || 0 });
              },
            }),
            el(SelectControl, {
              label: __('Sort mode', 'dracka'),
              help: __('Choose how artwork pieces are ordered before rendering and loading more.', 'dracka'),
              value: attributes.sortMode || 'newest',
              options: [
                { label: __('Newest first (publish date)', 'dracka'), value: 'newest' },
                { label: __('Manual order (menu order)', 'dracka'), value: 'manual' },
              ],
              onChange: function (value) {
                setAttributes({ sortMode: value || 'newest' });
              },
            }),
            el(TextControl, {
              label: __('Show more button label', 'dracka'),
              help: __('Label shown on the load button when more artwork is available.', 'dracka'),
              value: attributes.showMoreLabel || 'Show more',
              onChange: function (value) {
                setAttributes({ showMoreLabel: value || 'Show more' });
              },
            }),
            el(TextControl, {
              label: __('Go to gallery label', 'dracka'),
              help: __('Label used when cap is reached and the button turns into a gallery link.', 'dracka'),
              value: attributes.goToLibraryLabel || 'Go to gallery',
              onChange: function (value) {
                setAttributes({ goToLibraryLabel: value || 'Go to gallery' });
              },
            }),
            el(TextControl, {
              label: __('Go to gallery URL', 'dracka'),
              help: __('Destination URL used when the max cap is reached.', 'dracka'),
              value: attributes.goToLibraryUrl || '/gallery/artwork/',
              onChange: function (value) {
                setAttributes({ goToLibraryUrl: value || '/gallery/artwork/' });
              },
            })
          )
        ),
        el(
          'div',
          { className: 'dracka-latest-artwork-block-editor-placeholder' },
          __('Latest Artwork (dynamic): renders collapsed bar and loads artwork on the frontend.', 'dracka')
        )
      );
    },
    save: function () {
      // Dynamic block: frontend output is generated by the PHP render callback.
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor, window.wp.components);
