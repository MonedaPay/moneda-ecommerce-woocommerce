/**
 * MonedaPay payment method blocks integration
 */


if (typeof window.wc !== 'undefined' && 
    typeof window.wc.wcBlocksRegistry !== 'undefined' && 
    typeof window.wc.wcSettings !== 'undefined') {

    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { createElement } = window.wp.element;
    const { __ } = window.wp.i18n;
    const { getSetting } = window.wc.wcSettings;

    const settings = getSetting('monedapay_data', {});

const MonedaPayComponent = () => {
	return createElement(
		'div',
		{ className: 'monedapay-payment-method' },
		[
			settings.icon && createElement(
				'img',
				{
					key: 'icon',
					src: settings.icon,
					alt: settings.title,
					style: { maxHeight: '24px', marginRight: '8px' }
				}
			),
			createElement(
				'span',
				{ key: 'description' },
				settings.description
			)
		]
	);
};

// Register the payment method
registerPaymentMethod({
	name: 'monedapay',
	label: settings.title || __('Ari10 Pay', 'monedapay-payment-gateway'),
	content: createElement(MonedaPayComponent),
	edit: createElement(MonedaPayComponent),
	canMakePayment: () => true,
	ariaLabel: settings.title || __('Ari10 Pay', 'monedapay-payment-gateway'),
  supports: {
  }
});

}
