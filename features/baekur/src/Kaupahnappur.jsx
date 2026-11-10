import { useEffect, useRef, useState } from 'react';
import { hnappastillingar } from './leit.js';

let ui = null; // One Shopify Buy Button UI (and so one cart) for the whole page.

/** Loads Shopify's Buy Button script once and builds its UI with the shop's settings. */
function loadUi( k ) {
	if ( ! ui ) {
		ui = new Promise( ( resolve, reject ) => {
			const ready = () =>
				window.ShopifyBuy.UI.onReady( window.ShopifyBuy.buildClient( { domain: k.domain, storefrontAccessToken: k.storefrontAccessToken } ) ).then( resolve, reject );
			if ( window.ShopifyBuy?.UI ) {
				ready();
				return;
			}
			const s = document.createElement( 'script' );
			s.async = true;
			s.src = k.skrifta;
			s.onload = ready;
			s.onerror = reject;
			document.head.append( s );
		} );
		ui.catch( () => {
			ui = null; // Try again next time a book is opened.
		} );
	}
	return ui;
}

/**
 * The shop's own Buy Button for one product: options, price, quantity and "Bæta í körfu", with the cart on this page.
 * While it loads, or when the shop can not be reached, the plain link to the product is shown instead.
 * @param {Function} onAdded Called when something was put in the cart (the viewer then closes, so the cart is seen).
 */
export default function Kaupahnappur( { kaupahnappur, vara, onAdded } ) {
	const node = useRef( null );
	const [ ready, setReady ] = useState( false );
	const added = useRef( onAdded );
	added.current = onAdded;

	useEffect( () => {
		if ( ! kaupahnappur?.storefrontAccessToken || ! vara?.id ) {
			return undefined;
		}
		let gone = false;
		let shop = null;
		let check = 0;
		loadUi( kaupahnappur )
			.then( ( u ) => {
				if ( gone || ! node.current ) {
					return null;
				}
				shop = u;
				const options = hnappastillingar( kaupahnappur.options, () => added.current?.() );
				return u.createComponent( 'product', { id: vara.id, node: node.current, moneyFormat: kaupahnappur.moneyFormat, options } );
			} )
			.then( () => {
				// createComponent resolves with nothing, even when the button failed to draw: it is ready when its
				// frame has text in it (textContent: innerText is empty while the box is hidden).
				const drawn = () => Boolean( node.current?.querySelector( 'iframe' )?.contentDocument?.body?.textContent.trim() );
				let tries = 0;
				check = window.setInterval( () => {
					if ( gone || drawn() || ++tries > 20 ) {
						window.clearInterval( check );
						if ( ! gone ) {
							setReady( drawn() );
						}
					}
				}, 250 );
			} )
			.catch( () => {} );
		return () => {
			gone = true;
			window.clearInterval( check );
			try {
				shop?.destroyComponent( 'product', vara.id );
			} catch {
				// The node is gone with the viewer anyway.
			}
		};
	}, [ kaupahnappur, vara?.id ] );

	return (
		<>
			<div ref={ node } className="vs-bk-kaupa" hidden={ ! ready } />
			<a className={ ready ? 'vs-bk__tengill' : 'vs-bk__takki' } href={ vara.slod } target="_blank" rel="noopener">
				{ ready ? 'Opna vöruna í vefverslun Háskólaprents' : 'Kaupa í vefverslun' }
			</a>
		</>
	);
}
