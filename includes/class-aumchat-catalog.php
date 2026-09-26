<?php
/**
 * Reads the products that live in this WordPress site and pushes them to AumChat,
 * so the chat widget can answer questions about them and show product cards.
 *
 * Two sources, detected — never assumed:
 *   · WooCommerce   the `product` post type
 *   · AumNexCart    the `aum_nexcart_product` post type (enquiry-style catalogue, no cart)
 *
 * Neither installed: this class does nothing at all. A brochure site has no products,
 * and pushing an empty list would wipe the catalogue the shop owner may have typed by hand.
 *
 * @package AumChat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Catalogue reader and pusher.
 */
class AumChat_Catalog {

	const CRON_HOOK = 'aumchat_push_catalog';

	/**
	 * How many published products the last read found, before the cap.
	 *
	 * @var int
	 */
	private static $published = 0;
	const BATCH     = 200;

	/**
	 * Hooks the daily push.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'push' ) );
	}

	/**
	 * Schedules the daily sync.
	 *
	 * Registering the handler is not the same as scheduling it: without this, the hook simply never
	 * fires, while the manual button keeps working — so everything looks fine and the catalogue
	 * quietly goes stale. Prices are the worst thing to be stale about, because the widget will
	 * keep quoting yesterday's number to real shoppers and nothing anywhere reports it.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Leaves no orphaned schedule behind.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Which catalogues this site has. Detected by post type, never by URL:
	 * NexCart lets the site owner change its URL prefix in its own settings.
	 *
	 * Both can be present — a shop may sell simple items through WooCommerce and quote larger
	 * ones through NexCart. Picking only one would leave half the catalogue unanswerable,
	 * and nothing would say so.
	 *
	 * @return array List of 'nexcart' and/or 'woo'.
	 */
	public static function sources() {
		$found = array();
		if ( post_type_exists( 'aum_nexcart_product' ) ) {
			$found[] = 'nexcart';
		}
		if ( post_type_exists( 'product' ) && class_exists( 'WooCommerce' ) ) {
			$found[] = 'woo';
		}
		return $found;
	}

	/**
	 * First catalogue found, for wording in the admin screen.
	 *
	 * @return string 'nexcart', 'woo', or '' when there is no catalogue.
	 */
	public static function source() {
		$found = self::sources();
		return $found ? $found[0] : '';
	}

	/**
	 * Reads every published product of the active source.
	 *
	 * @param string|null $source Which catalogue to read; defaults to the first one found.
	 * @return array List of product arrays ready for the AumChat API.
	 */
	public static function read( $source = null ) {
		$source = $source ? $source : self::source();
		if ( '' === $source ) {
			return array();
		}

		$type = 'nexcart' === $source ? 'aum_nexcart_product' : 'product';
		/*
		 * Translations made by AumLang are separate posts of the same type, tied to the original by
		 * _aumlang_source_id. They are excluded here, in the query, rather than skipped in the loop
		 * below, for two reasons that both bite on multilingual shops:
		 *
		 *   · found_posts then counts what the reader would actually take, so "how many did not fit"
		 *     is a real number. Counting every published post instead told a four-language shop with
		 *     100 products that 300 of them were missing, on a site where the cap never fired.
		 *   · the cap's 2000 slots go to real products. Filtering afterwards let the translations
		 *     eat three quarters of them.
		 */
		$query = new WP_Query(
			array(
				'post_type'           => $type,
				'post_status'         => 'publish',
				'posts_per_page'      => 2000,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => false,
				'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one NOT EXISTS on an indexed meta key, run at most once a day.
					array(
						'key'     => '_aumlang_source_id',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$ids             = $query->posts;
		self::$published = (int) $query->found_posts;
		$items = array();

		foreach ( $ids as $id ) {
			/* Belt and braces: the query above already excludes translations, so this never fires. */
			if ( get_post_meta( $id, '_aumlang_source_id', true ) ) {
				continue;
			}
			$item = 'nexcart' === $source ? self::read_nexcart( $id ) : self::read_woo( $id );
			if ( $item ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * One NexCart product.
	 *
	 * @param int $id Post ID.
	 * @return array|null
	 */
	private static function read_nexcart( $id ) {
		$name = get_the_title( $id );
		if ( '' === trim( $name ) ) {
			return null;
		}

		/*
		 * Price. NexCart states its intent explicitly in _aum_nexcart_price_type, so there is
		 * no guessing from empty values:
		 *   hidden  no price is shown at all — and min/max may still hold stale numbers,
		 *           so they must not be read. Quoting a price the shop hides would be worse
		 *           than saying nothing.
		 *   text    a sentence replaces the price, e.g. "Contact for price".
		 *   range   min–max, with a currency SYMBOL (not an ISO code).
		 */
		$price_type = (string) get_post_meta( $id, '_aum_nexcart_price_type', true );
		$symbol     = (string) get_post_meta( $id, '_aum_nexcart_price_currency', true );
		$price      = null;
		if ( 'text' === $price_type ) {
			$price = (string) get_post_meta( $id, '_aum_nexcart_price_text', true );
		} elseif ( 'range' === $price_type ) {
			$min = (string) get_post_meta( $id, '_aum_nexcart_price_min', true );
			$max = (string) get_post_meta( $id, '_aum_nexcart_price_max', true );
			if ( '' !== $min ) {
				$price = $symbol . $min . ( '' !== $max && $max !== $min ? ' – ' . $symbol . $max : '' );
			}
		}

		$specs = array();
		$attrs = json_decode( (string) get_post_meta( $id, '_aum_nexcart_attributes', true ), true );
		if ( is_array( $attrs ) ) {
			foreach ( $attrs as $row ) {
				if ( isset( $row['key'], $row['value'] ) && '' !== trim( (string) $row['value'] ) ) {
					$specs[] = trim( (string) $row['key'] ) . ': ' . trim( (string) $row['value'] );
				}
			}
		}

		$terms      = get_the_terms( $id, 'aum_nexcart_category' );
		$categories = is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();

		return array(
			'externalId'  => (string) $id,
			'name'        => $name,
			'price'       => $price,
			/* The currency field is for ISO codes; NexCart stores a symbol, which is already inside price. */
			'currency'    => null,
			'category'    => $categories ? implode( ' / ', $categories ) : null,
			'sku'         => null,
			'url'         => get_permalink( $id ),
			'imageUrl'    => self::nexcart_image( $id ),
			'summary'     => self::text( get_the_excerpt( $id ), 500 ),
			'description' => self::text( get_post_field( 'post_content', $id ), 18000 ),
			'specs'       => $specs ? implode( "\n", $specs ) : null,
			'features'    => null,
			/* NexCart has no stock concept at all. Inventing one would show shoppers a promise nobody made. */
			'stock'       => null,
		);
	}

	/**
	 * First usable image of a NexCart product.
	 *
	 * Rows in _aum_nexcart_media may carry only an attachment_id, with no url, so the
	 * url has to be resolved rather than read.
	 *
	 * @param int $id Post ID.
	 * @return string|null
	 */
	private static function nexcart_image( $id ) {
		$media = json_decode( (string) get_post_meta( $id, '_aum_nexcart_media', true ), true );
		if ( is_array( $media ) ) {
			foreach ( $media as $row ) {
				if ( isset( $row['type'] ) && 'image' !== $row['type'] ) {
					continue;
				}
				if ( ! empty( $row['url'] ) ) {
					return (string) $row['url'];
				}
				if ( ! empty( $row['attachment_id'] ) ) {
					$url = wp_get_attachment_url( (int) $row['attachment_id'] );
					if ( $url ) {
						return $url;
					}
				}
			}
		}
		$thumb = get_the_post_thumbnail_url( $id, 'large' );
		return $thumb ? $thumb : null;
	}

	/**
	 * One WooCommerce product.
	 *
	 * @param int $id Post ID.
	 * @return array|null
	 */
	private static function read_woo( $id ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( ! $product ) {
			return null;
		}

		/*
		 * An empty price means the shop has not set one — quite common for made-to-order items.
		 * It is left empty rather than turned into 0, because 0 reads as "free" to a shopper.
		 */
		$price = $product->get_price();
		$terms = get_the_terms( $id, 'product_cat' );

		return array(
			'externalId'  => (string) $id,
			'name'        => $product->get_name(),
			'price'       => ( '' === $price || null === $price ) ? null : (string) $price,
			'currency'    => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : null,
			'category'    => is_array( $terms ) ? implode( ' / ', wp_list_pluck( $terms, 'name' ) ) : null,
			'sku'         => $product->get_sku() ? $product->get_sku() : null,
			'url'         => get_permalink( $id ),
			'imageUrl'    => get_the_post_thumbnail_url( $id, 'large' ) ? get_the_post_thumbnail_url( $id, 'large' ) : null,
			'summary'     => self::text( $product->get_short_description(), 500 ),
			'description' => self::text( $product->get_description(), 18000 ),
			'specs'       => null,
			'features'    => null,
			'stock'       => $product->is_in_stock() ? 'in' : 'out',
		);
	}

	/**
	 * HTML to plain text, trimmed to a length the API accepts.
	 *
	 * @param string $html Raw HTML.
	 * @param int    $max  Maximum length.
	 * @return string|null
	 */
	private static function text( $html, $max ) {
		$plain = trim( wp_strip_all_tags( (string) $html, true ) );
		if ( '' === $plain ) {
			return null;
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $plain, 0, $max ) : substr( $plain, 0, $max );
	}

	/**
	 * Sends the catalogue to AumChat.
	 *
	 * @return array{ok:bool,count:int,message:string}
	 */
	public static function push() {
		$settings = aumchat_get_settings();
		$token    = isset( $settings['push_token'] ) ? trim( (string) $settings['push_token'] ) : '';
		if ( '' === $token ) {
			return array(
				'ok'      => false,
				'count'   => 0,
				'message' => __( 'Add the sync token from your AumChat dashboard first.', 'aumchat' ),
			);
		}

		$sources = self::sources();
		if ( ! $sources ) {
			return array(
				'ok'      => false,
				'count'   => 0,
				'message' => __( 'No product catalogue found on this site.', 'aumchat' ),
			);
		}

		$total    = 0;
		$messages = array();
		foreach ( $sources as $source ) {
			$one = self::push_one( $token, $source );
			if ( ! $one['ok'] ) {
				return $one;
			}
			$total     += $one['count'];
			$messages[] = $one['message'];
		}

		/*
		 * Goes through the shared helper on purpose. Writing the option directly would be a second way
		 * of doing the same thing, and the first one already grew a bug: whole-option writes quietly
		 * dropped fields the caller did not list.
		 */
		aumchat_save_settings(
			array(
				'push_at'    => time(),
				'push_count' => $total,
			)
		);

		return array(
			'ok'      => true,
			'count'   => $total,
			'message' => implode( ' ', $messages ),
		);
	}

	/**
	 * Sends one catalogue.
	 *
	 * @param string $token  Sync token.
	 * @param string $source 'nexcart' or 'woo'.
	 * @return array{ok:bool,count:int,message:string}
	 */
	private static function push_one( $token, $source ) {
		$items = self::read( $source );
		/*
		 * Nothing to send is not the same as "send nothing": an empty list would delete every
		 * synced product on the AumChat side. A catalogue that reads as empty is far more likely
		 * to be a broken read than a shop that genuinely removed all of its products.
		 */
		if ( ! $items ) {
			return array(
				'ok'      => false,
				'count'   => 0,
				'message' => __( 'No published products were found, so nothing was sent.', 'aumchat' ),
			);
		}

		$response = wp_remote_post(
			AUMCHAT_SERVICE . '/api/plugin/products',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'    => wp_json_encode(
					array(
						'source'   => $source,
						'products' => $items,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'count'   => 0,
				'message' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code ) {
			return array(
				'ok'      => false,
				'count'   => 0,
				'message' => isset( $body['error'] ) ? (string) $body['error'] : sprintf(
					/* translators: %d: HTTP status code returned by the AumChat service. */
					__( 'AumChat replied with HTTP %d.', 'aumchat' ),
					$code
				),
			);
		}

		$count   = isset( $body['count'] ) ? (int) $body['count'] : 0;
		$skipped = isset( $body['skipped'] ) ? (int) $body['skipped'] : 0;
		/* Products this site has but the reader never looked at, because of the 2000 cap. */
		/*
		 * Two different reasons a product can be missing, and they need different actions from the
		 * shop owner, so they are never added together: the cap is ours, the plan limit is theirs.
		 */
		$beyond = max( 0, self::$published - count( $items ) );
		$notes  = array();
		if ( $beyond > 0 ) {
			$notes[] = sprintf(
				/* translators: %d: number of products beyond the plugin's own limit. */
				__( '%d were not read at all: this plugin syncs the first 2000 products.', 'aumchat' ),
				$beyond
			);
		}
		if ( $skipped > 0 ) {
			$notes[] = sprintf(
				/* translators: %d: number of products refused because of the AumChat plan limit. */
				__( '%d were refused because your AumChat plan has a lower product limit.', 'aumchat' ),
				$skipped
			);
		}
		if ( $notes ) {
			return array(
				'ok'      => true,
				'count'   => $count,
				'message' => sprintf(
					/* translators: 1: number of products sent, 2: catalogue name. */
					__( 'Sent %1$d products from %2$s.', 'aumchat' ),
					$count,
					'nexcart' === $source ? 'AumNexCart' : 'WooCommerce'
				) . ' ' . implode( ' ', $notes ) . ' ' . __( 'The assistant will not know about those.', 'aumchat' ),
			);
		}

		return array(
			'ok'      => true,
			'count'   => $count,
			'message' => sprintf(
				/* translators: 1: number of products sent, 2: catalogue name. */
				__( 'Sent %1$d products from %2$s.', 'aumchat' ),
				$count,
				'nexcart' === $source ? 'AumNexCart' : 'WooCommerce'
			),
		);
	}

}
