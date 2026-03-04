(function (blocks, element, i18n, blockEditor, components) {
  const el = element.createElement;
  const __ = i18n.__;
  const InspectorControls = blockEditor.InspectorControls;
  const PanelBody = components.PanelBody;
  const TextControl = components.TextControl;
  const RangeControl = components.RangeControl;
  const SelectControl = components.SelectControl;

  blocks.registerBlockType('dracka/latest-issues', {
    apiVersion: 2,
    title: __('Latest Issues', 'dracka'),
    description: __('Collapsible latest issues grid with incremental loading.', 'dracka'),
    icon: 'book-alt',
    category: 'widgets',
    supports: {
      html: false,
    },
    attributes: {
      title: {
        type: 'string',
        default: 'Latest Issues',
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
        default: 'Go to library',
      },
      goToLibraryUrl: {
        type: 'string',
        default: '/library/issues/',
      },
    },
    edit: function (props) {
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
              title: __('Latest Issues Settings', 'dracka'),
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
              label: __('Initial issues visible', 'dracka'),
              help: __('How many latest issues are rendered on first open.', 'dracka'),
              value: attributes.initialCount || 8,
              min: 1,
              max: 24,
              onChange: function (value) {
                setAttributes({ initialCount: Number(value) || 8 });
              },
            }),
            el(RangeControl, {
              label: __('Load more amount', 'dracka'),
              help: __('How many additional issues are fetched on each Show more click.', 'dracka'),
              value: attributes.increment || 8,
              min: 1,
              max: 24,
              onChange: function (value) {
                setAttributes({ increment: Number(value) || 8 });
              },
            }),
            el(RangeControl, {
              label: __('Maximum items shown in block', 'dracka'),
              help: __('Set to 0 for unlimited. If there are more items than this cap, Show more turns into Go to library.', 'dracka'),
              value: attributes.maxItemsCap || 0,
              min: 0,
              max: 200,
              onChange: function (value) {
                setAttributes({ maxItemsCap: Number(value) || 0 });
              },
            }),
            el(SelectControl, {
              label: __('Sort mode', 'dracka'),
              help: __('Choose how issues are ordered before rendering and loading more.', 'dracka'),
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
              help: __('Label shown on the load button when more issues are available.', 'dracka'),
              value: attributes.showMoreLabel || 'Show more',
              onChange: function (value) {
                setAttributes({ showMoreLabel: value || 'Show more' });
              },
            }),
            el(TextControl, {
              label: __('Go to library label', 'dracka'),
              help: __('Label used when cap is reached and the button turns into a library link.', 'dracka'),
              value: attributes.goToLibraryLabel || 'Go to library',
              onChange: function (value) {
                setAttributes({ goToLibraryLabel: value || 'Go to library' });
              },
            }),
            el(TextControl, {
              label: __('Go to library URL', 'dracka'),
              help: __('Destination URL used when the max cap is reached.', 'dracka'),
              value: attributes.goToLibraryUrl || '/library/issues/',
              onChange: function (value) {
                setAttributes({ goToLibraryUrl: value || '/library/issues/' });
              },
            })
          )
        ),
        el(
          'div',
          { className: 'dracka-latest-issues-block-editor-placeholder' },
          __('Latest Issues (dynamic): renders collapsed bar and loads issues on the frontend.', 'dracka')
        )
      );
    },
    save: function () {
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor, window.wp.components);
