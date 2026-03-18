(function (blocks, element, i18n, blockEditor, components) {
  // Editor-side block registration for a server-rendered newsletter block.
  // Frontend markup and incremental loading are handled in PHP + main.js.
  const el = element.createElement;
  const __ = i18n.__;
  const InspectorControls = blockEditor.InspectorControls;
  const PanelBody = components.PanelBody;
  const TextControl = components.TextControl;
  const RangeControl = components.RangeControl;

  blocks.registerBlockType('dracka/newsletter', {
    apiVersion: 3,
    title: __('Dracka Newsletter', 'dracka'),
    description: __('Collapsible latest posts feed with incremental loading.', 'dracka'),
    icon: 'email-alt',
    category: 'widgets',
    supports: {
      html: false,
    },
    attributes: {
      title: {
        type: 'string',
        default: 'Newsletter',
      },
      initialCount: {
        type: 'number',
        default: 3,
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
        default: 'See all',
      },
      goToLibraryUrl: {
        type: 'string',
        default: '/blog/',
      },
    },
    edit: function (props) {
      // Inspector controls define loading strategy and CTA text used on frontend.
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
              title: __('Newsletter Settings', 'dracka'),
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
              label: __('Initial posts visible', 'dracka'),
              help: __('How many newest posts are rendered in the preview. The frontend caps this at 3.', 'dracka'),
              value: attributes.initialCount || 3,
              min: 1,
              max: 3,
              onChange: function (value) {
                setAttributes({ initialCount: Number(value) || 3 });
              },
            }),
            el(RangeControl, {
              label: __('Load more amount', 'dracka'),
              help: __('How many additional posts are fetched on each Show more click.', 'dracka'),
              value: attributes.increment || 8,
              min: 1,
              max: 24,
              onChange: function (value) {
                setAttributes({ increment: Number(value) || 8 });
              },
            }),
            el(RangeControl, {
              label: __('Maximum items shown in block', 'dracka'),
              help: __('Set to 0 for unlimited. If there are more posts than this cap, Show more turns into Go to blog.', 'dracka'),
              value: attributes.maxItemsCap || 0,
              min: 0,
              max: 200,
              onChange: function (value) {
                setAttributes({ maxItemsCap: Number(value) || 0 });
              },
            }),
            el(TextControl, {
              label: __('Show more button label', 'dracka'),
              help: __('Label shown on the load button when more posts are available.', 'dracka'),
              value: attributes.showMoreLabel || 'Show more',
              onChange: function (value) {
                setAttributes({ showMoreLabel: value || 'Show more' });
              },
            }),
            el(TextControl, {
              label: __('Go to blog label', 'dracka'),
              help: __('Label used for the preview CTA shown before the last visible post.', 'dracka'),
              value: attributes.goToLibraryLabel || 'See all',
              onChange: function (value) {
                setAttributes({ goToLibraryLabel: value || 'See all' });
              },
            }),
            el(TextControl, {
              label: __('Go to blog URL', 'dracka'),
              help: __('Destination URL used when the max cap is reached.', 'dracka'),
              value: attributes.goToLibraryUrl || '/blog/',
              onChange: function (value) {
                setAttributes({ goToLibraryUrl: value || '/blog/' });
              },
            })
          )
        ),
        el(
          'div',
          { className: 'dracka-newsletter-block-editor-placeholder' },
          __('Newsletter (dynamic): renders collapsed bar and loads latest posts on the frontend.', 'dracka')
        )
      );
    },
    save: function () {
      // Dynamic block: frontend output is generated by the PHP render callback.
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor, window.wp.components);
