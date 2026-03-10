(function (blocks, element, i18n, blockEditor, components) {
  // Editor-side block registration for a server-rendered news ticker block.
  // The frontend animation loop and sanitized item output are produced in PHP/CSS.
  const el = element.createElement;
  const __ = i18n.__;
  const InspectorControls = blockEditor.InspectorControls;
  const PanelBody = components.PanelBody;
  const RangeControl = components.RangeControl;

  blocks.registerBlockType('dracka/news-ticker', {
    apiVersion: 2,
    title: __('News Ticker', 'dracka'),
    description: __('Displays active ticker items as a continuous right-to-left marquee.', 'dracka'),
    icon: 'megaphone',
    category: 'widgets',
    supports: {
      html: false,
    },
    attributes: {
      speedSeconds: {
        type: 'number',
        default: 28,
      },
    },
    edit: function (props) {
      // The editor only configures speed; ticker content comes from active ticker CPT entries.
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
              title: __('News Ticker Settings', 'dracka'),
              initialOpen: true,
            },
            el(RangeControl, {
              label: __('Scroll speed (seconds per loop)', 'dracka'),
              help: __('Lower values scroll faster. Higher values scroll slower.', 'dracka'),
              value: Number(attributes.speedSeconds) || 28,
              min: 8,
              max: 120,
              onChange: function (value) {
                setAttributes({ speedSeconds: Number(value) || 28 });
              },
            })
          )
        ),
        el(
          'div',
          { className: 'dracka-news-ticker-block-editor-placeholder' },
          __('News Ticker (dynamic): renders active published ticker items from the ticker CPT.', 'dracka')
        )
      );
    },
    save: function () {
      // Dynamic block: do not serialize static HTML in post content.
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor, window.wp.components);