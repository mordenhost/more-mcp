<?php

namespace Mordenhost_MCP\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema_Validator {

	const MAX_DEPTH = 12;

	const MAX_INPUT_BYTES = 262144;

	const DATE_PROPS = array(
		'datePublished', 'dateModified', 'dateCreated', 'startDate', 'endDate',
		'uploadDate', 'datePosted', 'validThrough', 'validFrom', 'priceValidUntil',
		'birthDate', 'foundingDate', 'expires',
	);

	const NUMBER_PROPS = array(
		'ratingValue', 'bestRating', 'worstRating', 'ratingCount', 'reviewCount',
		'price', 'lowPrice', 'highPrice', 'position',
	);

	const URL_PROPS = array(
		'url', 'logo', 'image', 'thumbnailUrl', 'contentUrl', 'embedUrl',
		'sameAs', 'item', 'mainEntityOfPage', 'hasMap',
	);

	const SINGLETON_BASES = array(
		'WebSite', 'WebPage', 'Article', 'BreadcrumbList', 'FAQPage',
		'Organization', 'LocalBusiness', 'Product',
	);

	private static function rules(): array {
		return array(
			'Article'             => array(
				'required'    => array(),
				'recommended' => array( 'headline', 'image', 'datePublished', 'author' ),
			),
			'WebPage'             => array(
				'required'    => array(),
				'recommended' => array( 'name', 'url' ),
			),
			'WebSite'             => array(
				'required'    => array(),
				'recommended' => array( 'name', 'url' ),
			),
			'FAQPage'             => array(
				'required'    => array( 'mainEntity' ),
				'recommended' => array(),
				'children'    => array( 'mainEntity' => 'Question' ),
			),
			'QAPage'              => array(
				'required'    => array( 'mainEntity' ),
				'recommended' => array(),
				'children'    => array( 'mainEntity' => 'Question' ),
			),
			'ProfilePage'         => array(
				'required'    => array( 'mainEntity' ),
				'recommended' => array(),
			),
			'Question'            => array(
				'required'    => array( 'name', array( 'acceptedAnswer', 'suggestedAnswer' ) ),
				'recommended' => array(),
				'children'    => array(
					'acceptedAnswer'  => 'Answer',
					'suggestedAnswer' => 'Answer',
				),
			),
			'Answer'              => array(
				'required'    => array( 'text' ),
				'recommended' => array(),
			),
			'LocalBusiness'       => array(
				'required'    => array( 'name', 'address' ),
				'recommended' => array( 'telephone', 'url', 'image', 'geo', 'openingHoursSpecification', 'priceRange' ),
				'children'    => array(
					'address'         => 'PostalAddress',
					'geo'             => 'GeoCoordinates',
					'aggregateRating' => 'AggregateRating',
				),
			),
			'Organization'        => array(
				'required'    => array(),
				'recommended' => array( 'name', 'url', 'logo' ),
				'children'    => array( 'address' => 'PostalAddress' ),
			),
			'Person'              => array(
				'required'    => array( 'name' ),
				'recommended' => array(),
			),
			'PostalAddress'       => array(
				'required'    => array(),
				'recommended' => array( 'streetAddress', 'addressLocality', 'postalCode', 'addressCountry' ),
			),
			'GeoCoordinates'      => array(
				'required'    => array( 'latitude', 'longitude' ),
				'recommended' => array(),
			),
			'Product'             => array(
				'required'    => array( 'name', array( 'offers', 'review', 'aggregateRating' ) ),
				'recommended' => array( 'image', 'description' ),
				'children'    => array(
					'offers'          => 'Offer',
					'aggregateRating' => 'AggregateRating',
					'review'          => 'Review',
				),
			),
			'Offer'               => array(
				'required'    => array( array( 'price', 'priceSpecification' ) ),
				'recommended' => array( 'priceCurrency', 'availability' ),
			),
			'AggregateOffer'      => array(
				'required'    => array( 'lowPrice', 'priceCurrency' ),
				'recommended' => array( 'highPrice', 'offerCount' ),
			),
			'AggregateRating'     => array(
				'required'    => array( 'ratingValue', array( 'ratingCount', 'reviewCount' ) ),
				'recommended' => array( 'bestRating' ),
			),
			'Review'              => array(
				'required'    => array( 'author', 'reviewRating' ),
				'recommended' => array( 'itemReviewed' ),
				'children'    => array( 'reviewRating' => 'Rating' ),
			),
			'Rating'              => array(
				'required'    => array( 'ratingValue' ),
				'recommended' => array( 'bestRating' ),
			),
			'Event'               => array(
				'required'    => array( 'name', 'startDate', 'location' ),
				'recommended' => array( 'endDate', 'eventStatus', 'image', 'description', 'offers', 'organizer' ),
			),
			'Place'               => array(
				'required'    => array( 'address' ),
				'recommended' => array( 'name' ),
			),
			'VirtualLocation'     => array(
				'required'    => array( 'url' ),
				'recommended' => array(),
			),
			'BreadcrumbList'      => array(
				'required'    => array( 'itemListElement' ),
				'recommended' => array(),
				'children'    => array( 'itemListElement' => 'ListItem' ),
			),
			'ListItem'            => array(
				'required'    => array( 'position' ),
				'recommended' => array( 'name', 'item' ),
			),
			'Recipe'              => array(
				'required'    => array( 'name', 'image' ),
				'recommended' => array( 'author', 'datePublished', 'description', 'recipeIngredient', 'recipeInstructions' ),
			),
			'HowTo'               => array(
				'required'    => array( 'name', 'step' ),
				'recommended' => array(),
			),
			'VideoObject'         => array(
				'required'    => array( 'name', 'thumbnailUrl', 'uploadDate' ),
				'recommended' => array( 'description', 'contentUrl', 'embedUrl', 'duration' ),
			),
			'ImageObject'         => array(
				'required'    => array( array( 'contentUrl', 'url' ) ),
				'recommended' => array(),
			),
			'JobPosting'          => array(
				'required'    => array( 'title', 'description', 'datePosted', 'hiringOrganization', array( 'jobLocation', 'jobLocationType' ) ),
				'recommended' => array( 'validThrough', 'employmentType', 'baseSalary' ),
			),
			'Course'              => array(
				'required'    => array( 'name', 'description' ),
				'recommended' => array( 'provider' ),
			),
			'SoftwareApplication' => array(
				'required'    => array( 'name' ),
				'recommended' => array( 'offers', 'aggregateRating', 'applicationCategory', 'operatingSystem' ),
			),
			'Service'             => array(
				'required'    => array(),
				'recommended' => array( 'name', 'provider', 'areaServed', 'serviceType' ),
			),
		);
	}

	private static function subtypes(): array {
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}
		$families = array(
			'Article'       => array(
				'NewsArticle', 'BlogPosting', 'TechArticle', 'ScholarlyArticle', 'Report',
				'SatiricalArticle', 'AnalysisNewsArticle', 'OpinionNewsArticle',
				'ReportageNewsArticle', 'ReviewNewsArticle', 'LiveBlogPosting',
				'SocialMediaPosting', 'DiscussionForumPosting',
			),
			'WebPage'       => array(
				'AboutPage', 'CheckoutPage', 'CollectionPage', 'ContactPage', 'ItemPage',
				'MedicalWebPage', 'RealEstateListing', 'SearchResultsPage',
			),
			'LocalBusiness' => array(
				'AnimalShelter', 'ArchiveOrganization', 'AutomotiveBusiness', 'AutoRepair',
				'AutoDealer', 'ChildCare', 'Dentist', 'DryCleaningOrLaundry', 'EmergencyService',
				'EmploymentAgency', 'EntertainmentBusiness', 'FinancialService', 'AccountingService',
				'FoodEstablishment', 'Restaurant', 'Bakery', 'BarOrPub', 'CafeOrCoffeeShop',
				'FastFoodRestaurant', 'GovernmentOffice', 'HealthAndBeautyBusiness', 'BeautySalon',
				'DaySpa', 'HairSalon', 'HomeAndConstructionBusiness', 'Electrician', 'Plumber',
				'HVACBusiness', 'RoofingContractor', 'GeneralContractor', 'HousePainter',
				'Locksmith', 'MovingCompany', 'InternetCafe', 'LegalService', 'Attorney', 'Notary',
				'Library', 'LodgingBusiness', 'Hotel', 'Motel', 'BedAndBreakfast', 'Hostel', 'Resort',
				'MedicalBusiness', 'MedicalClinic', 'Physician', 'Optician', 'Pharmacy',
				'ProfessionalService', 'RadioStation', 'RealEstateAgent', 'RecyclingCenter',
				'SelfStorage', 'ShoppingCenter', 'SportsActivityLocation', 'ExerciseGym', 'Store',
				'ClothingStore', 'ElectronicsStore', 'Florist', 'FurnitureStore', 'GroceryStore',
				'HardwareStore', 'JewelryStore', 'PetStore', 'TelevisionStation',
				'TouristInformationCenter', 'TravelAgency',
			),
			'Organization'  => array(
				'Corporation', 'NGO', 'EducationalOrganization', 'GovernmentOrganization',
				'NewsMediaOrganization', 'OnlineBusiness', 'OnlineStore', 'MedicalOrganization',
				'SportsOrganization', 'PerformingGroup', 'Airline', 'Consortium', 'LibrarySystem',
				'WorkersUnion', 'ResearchOrganization',
			),
			'Event'         => array(
				'BusinessEvent', 'ChildrensEvent', 'ComedyEvent', 'CourseInstance', 'DanceEvent',
				'DeliveryEvent', 'EducationEvent', 'ExhibitionEvent', 'Festival', 'FoodEvent',
				'LiteraryEvent', 'MusicEvent', 'PublicationEvent', 'SaleEvent', 'ScreeningEvent',
				'SocialEvent', 'SportsEvent', 'TheaterEvent', 'VisualArtsEvent',
			),
			'Product'       => array( 'IndividualProduct', 'ProductModel', 'SomeProducts', 'Vehicle', 'Car' ),
			'Review'        => array( 'CriticReview', 'EmployerReview', 'UserReview' ),
			'Rating'        => array( 'EndorsementRating' ),
			'SoftwareApplication' => array( 'MobileApplication', 'WebApplication', 'VideoGame' ),
		);
		$map = array();
		foreach ( $families as $base => $members ) {
			foreach ( $members as $member ) {
				$map[ $member ] = $base;
			}
		}
		return $map;
	}

	public static function base_type( string $type ): string {
		$rules = self::rules();
		if ( isset( $rules[ $type ] ) ) {
			return $type;
		}
		$sub = self::subtypes();
		return $sub[ $type ] ?? '';
	}

	

	
	public static function decode( $input ): array {
		$out = array(
			'nodes'   => array(),
			'context' => null,
			'errors'  => array(),
		);
		if ( is_string( $input ) ) {
			$trimmed = trim( $input );
			if ( '' === $trimmed ) {
				$out['errors'][] = 'The schema is empty.';
				return $out;
			}
			if ( strlen( $trimmed ) > self::MAX_INPUT_BYTES ) {
				$out['errors'][] = 'The schema is larger than ' . self::MAX_INPUT_BYTES . ' bytes.';
				return $out;
			}
			$decoded = json_decode( $trimmed, true, 64 );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$out['errors'][] = 'Invalid JSON: ' . json_last_error_msg() . '.';
				return $out;
			}
			$input = $decoded;
		} elseif ( is_object( $input ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This class stays free of WordPress calls (see the file docblock); wp_json_encode() is used when present.
			$json  = function_exists( 'wp_json_encode' ) ? wp_json_encode( $input ) : json_encode( $input );
			$input = json_decode( (string) $json, true, 64 );
		}

		if ( ! is_array( $input ) || array() === $input ) {
			$out['errors'][] = 'The schema must be a JSON object (or a list of objects).';
			return $out;
		}

		if ( self::is_list( $input ) ) {
			$out['nodes'] = $input;
			return $out;
		}
		if ( array_key_exists( '@graph', $input ) ) {
			$out['context'] = $input['@context'] ?? null;
			if ( ! is_array( $input['@graph'] ) || ! self::is_list( $input['@graph'] ) ) {
				$out['errors'][] = '@graph must be a list of objects.';
				return $out;
			}
			$out['nodes'] = $input['@graph'];
			return $out;
		}
		$out['nodes'] = array( $input );
		return $out;
	}

	

	
	public static function validate( $input, array $opts = array() ): array {
		$decoded = self::decode( $input );
		if ( $decoded['errors'] ) {
			return self::result( array( 'errors' => $decoded['errors'] ) );
		}
		$opts['context'] = $decoded['context'];
		return self::validate_nodes( $decoded['nodes'], $opts );
	}

	public static function validate_blocks( array $blocks, array $opts = array() ): array {
		$errors   = array();
		$nodes    = array();
		$contexts = array();
		foreach ( array_values( $blocks ) as $i => $raw ) {
			$decoded = self::decode( (string) $raw );
			foreach ( $decoded['errors'] as $error ) {
				$errors[] = 'Block ' . ( $i + 1 ) . ': ' . $error;
			}
			foreach ( $decoded['nodes'] as $node ) {
				$nodes[]    = $node;
				$contexts[] = $decoded['context'];
			}
		}
		$opts['require_context'] = true;
		$opts['contexts']        = $contexts;
		$result                  = self::validate_nodes( $nodes, $opts );
		if ( $errors ) {
			$result['errors'] = array_merge( $errors, $result['errors'] );
			$result['valid']  = false;
		}
		$result['blocks'] = count( $blocks );
		return $result;
	}

	public static function validate_nodes( array $nodes, array $opts = array() ): array {
		$state = array(
			'errors'    => array(),
			'warnings'  => array(),
			'unchecked' => array(),
			'ids'       => array(),
			'urls'      => array(),
		);
		$require   = ! array_key_exists( 'require_context', $opts ) || ! empty( $opts['require_context'] );
		$wrapper   = $opts['context'] ?? null;
		$contexts  = isset( $opts['contexts'] ) && is_array( $opts['contexts'] ) ? $opts['contexts'] : array();
		$site_host = strtolower( (string) ( $opts['site_host'] ?? '' ) );

		if ( null !== $wrapper && ! self::is_schema_context( $wrapper ) ) {
			$state['errors'][] = '@context must be "https://schema.org".';
		}

		$types  = array();
		$counts = array();
		$multi  = count( $nodes ) > 1;
		foreach ( array_values( $nodes ) as $i => $node ) {
			$label = $multi ? 'Node ' . ( $i + 1 ) : 'Node';
			if ( ! is_array( $node ) || self::is_list( $node ) ) {
				$state['errors'][] = $label . ': must be a JSON object.';
				continue;
			}
			$node_types = self::types_of( $node );
			if ( $node_types ) {
				$label .= ' (' . implode( ', ', $node_types ) . ')';
			}

			$context = array_key_exists( $i, $contexts ) && null !== $contexts[ $i ] ? $contexts[ $i ] : $wrapper;
			if ( isset( $node['@context'] ) ) {
				if ( ! self::is_schema_context( $node['@context'] ) ) {
					$state['errors'][] = $label . ': @context must be "https://schema.org".';
				}
				$context = $node['@context'];
			}
			if ( null === $context && $require ) {
				$state['errors'][] = $label . ': missing @context. A JSON-LD block needs "@context": "https://schema.org".';
			}

			if ( ! $node_types ) {
				$state['errors'][] = $label . ': missing @type.';
			}
			self::check_node( $node, $label, 0, $state, '' );

			if ( $node_types ) {
				$types[] = $node_types[0];
				$bases   = array();
				foreach ( $node_types as $type ) {
					$base = self::base_type( $type );
					if ( in_array( $base, self::SINGLETON_BASES, true ) ) {
						$bases[ $base ] = true;
					}
				}
				foreach ( array_keys( $bases ) as $base ) {
					$counts[ $base ] = ( $counts[ $base ] ?? 0 ) + 1;
				}
			}
		}

		foreach ( $counts as $base => $count ) {
			if ( $count > 1 ) {
				$state['warnings'][] = sprintf( 'Duplicate type: %d nodes are a %s (or a subtype of it). A page should describe one; search engines may pick one or ignore both. Merge them into one node.', $count, $base );
			}
		}
		foreach ( $state['ids'] as $id => $seen ) {
			$distinct = array_unique( $seen );
			if ( count( $distinct ) > 1 ) {
				$state['warnings'][] = sprintf( 'Conflicting types: @id "%s" is declared as %s.', $id, implode( ' and as ', $distinct ) );
			}
		}
		if ( '' !== $site_host ) {
			foreach ( array_unique( $state['urls'] ) as $url ) {
				$reason = self::stale_url_reason( $url, $site_host );
				if ( '' !== $reason ) {
					$state['warnings'][] = sprintf( 'Stale URL: %s %s.', $url, $reason );
				}
			}
		}

		$state['types'] = array_values( array_unique( $types ) );
		return self::result( $state );
	}

	private static function result( array $state ): array {
		$errors = array_values( array_unique( $state['errors'] ?? array() ) );
		return array(
			'valid'           => array() === $errors,
			'errors'          => $errors,
			'warnings'        => array_values( array_unique( $state['warnings'] ?? array() ) ),
			'types'           => $state['types'] ?? array(),
			'unchecked_types' => array_values( array_unique( $state['unchecked'] ?? array() ) ),
		);
	}

	private static function check_node( array $node, string $path, int $depth, array &$state, string $assumed ): void {
		if ( $depth > self::MAX_DEPTH ) {
			$state['errors'][] = $path . ': nested deeper than ' . self::MAX_DEPTH . ' levels.';
			return;
		}

		foreach ( array_keys( $node ) as $key ) {
			if ( ! is_string( $key ) || ! preg_match( '/^@?[A-Za-z][A-Za-z0-9_:.\-]*$/', $key ) ) {
				$state['errors'][] = $path . ': "' . self::clip( (string) $key ) . '" is not a valid property name.';
			}
		}

		if ( array_key_exists( '@type', $node ) && ! self::valid_type_value( $node['@type'] ) ) {
			$state['errors'][] = $path . ': @type must be a type name or a list of type names.';
		}
		$types = self::types_of( $node );
		if ( ! $types && '' !== $assumed && $depth > 0 ) {
			$state['warnings'][] = $path . ': no @type; expected ' . $assumed . '. Checked as ' . $assumed . '.';
			$types = array( $assumed );
		}

		if ( isset( $node['@id'] ) ) {
			if ( ! is_string( $node['@id'] ) || '' === trim( $node['@id'] ) ) {
				$state['errors'][] = $path . ': @id must be a non-empty string.';
			} elseif ( $types ) {
				$state['ids'][ $node['@id'] ][] = implode( '/', $types );
			}
		}

		$rules    = self::rules();
		$children = array();
		foreach ( $types as $type ) {
			$base = self::base_type( $type );
			if ( '' === $base ) {
				$state['unchecked'][] = $type;
				continue;
			}
			$rule = $rules[ $base ];
			foreach ( $rule['required'] as $required ) {
				$options = (array) $required;
				if ( ! self::any_present( $node, $options ) ) {
					$state['errors'][] = sprintf(
						'%s: %s is missing required property %s.',
						$path,
						$type,
						count( $options ) > 1 ? 'one of "' . implode( '", "', $options ) . '"' : '"' . $options[0] . '"'
					);
				}
			}
			foreach ( $rule['recommended'] as $recommended ) {
				if ( ! self::any_present( $node, (array) $recommended ) ) {
					$state['warnings'][] = sprintf( '%s: %s is missing recommended property "%s".', $path, $type, $recommended );
				}
			}
			foreach ( $rule['children'] ?? array() as $prop => $child_type ) {
				if ( ! isset( $children[ $prop ] ) ) {
					$children[ $prop ] = $child_type;
				}
			}
		}

		foreach ( $node as $prop => $value ) {
			if ( ! is_string( $prop ) ) {
				continue;
			}
			self::check_value( (string) $prop, $value, $path, $state );
			if ( '' !== $prop && '@' === $prop[0] ) {
				continue;
			}
			$child_type = $children[ $prop ] ?? '';
			if ( is_array( $value ) && ! self::is_list( $value ) ) {
				self::check_node( $value, $path . '.' . $prop, $depth + 1, $state, $child_type );
			} elseif ( is_array( $value ) ) {
				foreach ( $value as $index => $item ) {
					if ( is_array( $item ) && ! self::is_list( $item ) ) {
						self::check_node( $item, $path . '.' . $prop . '[' . $index . ']', $depth + 1, $state, $child_type );
					}
				}
			}
		}
	}

	private static function check_value( string $prop, $value, string $path, array &$state ): void {
		if ( is_array( $value ) && self::is_list( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! is_array( $item ) ) {
					self::check_value( $prop, $item, $path, $state );
				}
			}
			return;
		}
		if ( is_array( $value ) || null === $value || is_bool( $value ) ) {
			return;
		}

		if ( is_string( $value ) && preg_match( '#^https?://#i', $value ) ) {
			$state['urls'][] = $value;
		}

		if ( in_array( $prop, self::DATE_PROPS, true ) && is_string( $value ) && ! self::is_iso_date( $value ) ) {
			$state['errors'][] = sprintf( '%s: "%s" must be an ISO 8601 date (for example 2026-10-14 or 2026-10-14T09:00:00+00:00), got "%s".', $path, $prop, self::clip( $value ) );
		}

		if ( in_array( $prop, self::NUMBER_PROPS, true ) && ! self::is_number( $value ) ) {
			$hint = in_array( $prop, array( 'price', 'lowPrice', 'highPrice' ), true ) ? ' Put the currency in priceCurrency, not in the price.' : '';
			$state['errors'][] = sprintf( '%s: "%s" must be a number, got "%s".%s', $path, $prop, self::clip( (string) $value ), $hint );
		}

		if ( 'priceCurrency' === $prop && is_string( $value ) && ! preg_match( '/^[A-Z]{3}$/', $value ) ) {
			$state['warnings'][] = sprintf( '%s: "priceCurrency" should be a three-letter ISO 4217 code such as USD, got "%s".', $path, self::clip( $value ) );
		}

		if ( ( in_array( $prop, self::URL_PROPS, true ) || '@id' === $prop ) && is_string( $value ) ) {
			if ( preg_match( '#^\s*(javascript|data|vbscript):#i', $value ) ) {
				$state['errors'][] = sprintf( '%s: "%s" uses a script or data URL, which is never valid here.', $path, $prop );
			} elseif ( '@id' !== $prop && ! preg_match( '#^https?://[^\s/]+#i', $value ) ) {
				$state['warnings'][] = sprintf( '%s: "%s" should be an absolute http(s) URL, got "%s".', $path, $prop, self::clip( $value ) );
			}
		}
	}

	

	
	public static function types_of( array $node ): array {
		$type = $node['@type'] ?? null;
		if ( is_string( $type ) && '' !== trim( $type ) ) {
			return array( trim( $type ) );
		}
		if ( is_array( $type ) ) {
			$out = array();
			foreach ( $type as $t ) {
				if ( is_string( $t ) && '' !== trim( $t ) ) {
					$out[] = trim( $t );
				}
			}
			return $out;
		}
		return array();
	}

	private static function valid_type_value( $type ): bool {
		if ( is_string( $type ) ) {
			return (bool) preg_match( '/^[A-Za-z][A-Za-z0-9_:.\-]*$/', $type );
		}
		if ( ! is_array( $type ) || array() === $type || ! self::is_list( $type ) ) {
			return false;
		}
		foreach ( $type as $t ) {
			if ( ! is_string( $t ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9_:.\-]*$/', $t ) ) {
				return false;
			}
		}
		return true;
	}

	public static function is_schema_context( $context ): bool {
		if ( is_string( $context ) ) {
			return (bool) preg_match( '#^https?://schema\.org/?$#i', trim( $context ) );
		}
		if ( is_array( $context ) ) {
			if ( isset( $context['@vocab'] ) ) {
				return self::is_schema_context( $context['@vocab'] );
			}
			if ( self::is_list( $context ) ) {
				foreach ( $context as $item ) {
					if ( is_string( $item ) && self::is_schema_context( $item ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	private static function any_present( array $node, array $props ): bool {
		foreach ( $props as $prop ) {
			if ( ! array_key_exists( $prop, $node ) ) {
				continue;
			}
			$value = $node[ $prop ];
			if ( null === $value || ( is_string( $value ) && '' === trim( $value ) ) || ( is_array( $value ) && array() === $value ) ) {
				continue;
			}
			return true;
		}
		return false;
	}

	public static function is_iso_date( string $value ): bool {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+\-]\d{2}:?\d{2})?)?$/', trim( $value ) );
	}

	private static function is_number( $value ): bool {
		if ( is_int( $value ) || is_float( $value ) ) {
			return true;
		}
		return is_string( $value ) && (bool) preg_match( '/^\s*-?\d+(?:\.\d+)?\s*$/', $value );
	}

	public static function stale_url_reason( string $url, string $site_host ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- This class stays free of WordPress calls (see the file docblock); wp_parse_url() is used when present.
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		$host  = is_array( $parts ) && isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		if ( '' === $host || '' === $site_host || $host === $site_host ) {
			return '';
		}
		if ( 'localhost' === $host || preg_match( '/^(127\.|10\.|192\.168\.|0\.0\.0\.0$|\[?::1\]?$)/', $host ) ) {
			return 'points at a local address';
		}
		if ( preg_match( '/\.(local|localhost|test|invalid|example|lan|internal)$/', $host ) ) {
			return 'points at a development host';
		}
		if ( preg_match( '/^(staging|stage|stg|dev|develop|test|uat)[.\-]/', $host ) || preg_match( '/\.(wpengine\.com|wpenginepowered\.com|kinsta\.cloud|instawp\.xyz|flywheelsites\.com|pantheonsite\.io|cloudwaysapps\.com|wpcomstaging\.com|myftpupload\.com|tempurl\.host)$/', $host ) ) {
			return 'points at what looks like a staging host';
		}
		return '';
	}

	private static function is_list( array $value ): bool {
		return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	private static function clip( string $value ): string {
		return strlen( $value ) > 80 ? substr( $value, 0, 77 ) . '...' : $value;
	}
}
