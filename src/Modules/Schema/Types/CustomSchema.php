<?php

namespace Quinrel\WordPress\Modules\Schema\Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Modules\Schema\Contracts\SchemaTypeInterface;
use Quinrel\WordPress\Modules\Schema\AudioExtractor;
use Quinrel\WordPress\Modules\Schema\JsonLd;

/**
 * Generates custom point-and-click schemas (Product, FAQ, HowTo, LocalBusiness, Recipe, Event,
 * Podcast, Course, JobPosting, Book, Software, Restaurant, Service, Review, Movie, Music, Dataset, Person)
 * based on post-level metadata.
 */
class CustomSchema implements SchemaTypeInterface {

	public function getId(): string {
		return 'custom';
	}

	/**
	 * Determines if a custom schema is configured for the current post.
	 */
	public function isApplicable( ?int $postId = null ): bool {
		$id = $postId !== null ? $postId : get_the_ID();
		if ( ! $id ) {
			return false;
		}

		$schemaType = \Quinrel\WordPress\Modules\Schema\SchemaGenerator::getEffectivePrimarySchema( $id );
		return ! empty( $schemaType ) && $schemaType !== 'none';
	}

	/**
	 * Generates the schema payload.
	 */
	public function generate( ?int $postId = null ): array {
		$id = $postId !== null ? $postId : get_the_ID();
		if ( ! $id ) {
			return array();
		}

		$schemaType = \Quinrel\WordPress\Modules\Schema\SchemaGenerator::getEffectivePrimarySchema( $id );
		$schema     = array();

		switch ( $schemaType ) {
			case 'product':
				$schema = $this->generateProductSchema( $id );
				break;
			case 'faq':
				$schema = $this->generateFaqSchema( $id );
				break;
			case 'howto':
				$schema = $this->generateHowToSchema( $id );
				break;
			case 'localbusiness':
				$schema = $this->generateLocalBusinessSchema( $id );
				break;
			case 'recipe':
				$schema = $this->generateRecipeSchema( $id );
				break;
			case 'event':
				$schema = $this->generateEventSchema( $id );
				break;
			case 'podcast':
			case 'podcastepisode':
				$schema = $this->generatePodcastSchema( $id );
				break;
			case 'course':
				$schema = $this->generateCourseSchema( $id );
				break;
			case 'jobposting':
				$schema = $this->generateJobPostingSchema( $id );
				break;
			case 'book':
				$schema = $this->generateBookSchema( $id );
				break;
			case 'software':
			case 'softwareapplication':
				$schema = $this->generateSoftwareSchema( $id );
				break;
			case 'restaurant':
				$schema = $this->generateRestaurantSchema( $id );
				break;
			case 'service':
				$schema = $this->generateServiceSchema( $id );
				break;
			case 'review':
				$schema = $this->generateReviewSchema( $id );
				break;
			case 'movie':
				$schema = $this->generateMovieSchema( $id );
				break;
			case 'music':
			case 'musicrecording':
				$schema = $this->generateMusicSchema( $id );
				break;
			case 'dataset':
				$schema = $this->generateDatasetSchema( $id );
				break;
			case 'person':
				$schema = $this->generatePersonSchema( $id );
				break;
			case 'custom':
				$parsed = $this->parsedCustomJson( $id );
				if ( is_array( $parsed ) && 'nodes' === ( $parsed['kind'] ?? '' ) ) {
					return $parsed['nodes'];
				}
				$schema = array(
					'@type' => $this->customType( $id ),
					'@id'   => get_permalink( $id ) . '#custom',
				);
				break;
			default:
				$schema = array();
				break;
		}

		if ( ! empty( $schema ) ) {
			$schema = $this->mergeCustomProperties( $id, $schema );
		}

		return $schema;
	}

	/**
	 * Product Schema.
	 */
	private function generateProductSchema( int $postId ): array {
		if ( $this->wooCommerceOwnsProduct( $postId ) ) {
			return array();
		}

		$name     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_product_name', true ), get_the_title( $postId ) );
		$desc     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_product_description', true ), wp_strip_all_tags( get_the_excerpt( $postId ) ) );
		$price    = $this->textValue( get_post_meta( $postId, '_quinrel_schema_product_price', true ) );
		$currency = $this->textValue( get_post_meta( $postId, '_quinrel_schema_product_currency', true ), 'USD' );
		$rating   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_product_rating', true ) );
		$stock    = $this->textValue( get_post_meta( $postId, '_quinrel_schema_product_availability', true ), 'InStock' );

		if ( $name === '' || ( $price === '' && $rating === '' ) ) {
			return array();
		}

		$schema = array(
			'@type' => 'Product',
			'@id'   => get_permalink( $postId ) . '#product',
			'name'  => $name,
			'url'   => get_permalink( $postId ),
		);
		$schema = $this->putText( $schema, 'description', $desc );
		$image  = $this->thumbnailUrl( $postId );
		if ( $image !== '' ) {
			$schema['image'] = $image;
		}

		if ( $price !== '' && $currency !== '' ) {
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => $price,
				'priceCurrency' => $currency,
				'availability'  => 'https://schema.org/' . $stock,
				'url'           => get_permalink( $postId ),
			);
		}

		if ( $rating !== '' ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating,
				'bestRating'  => 5,
				'ratingCount' => 1,
			);
		}

		return $schema;
	}

	/**
	 * WooCommerce prints its own Product node on a product URL. A second Product node is not added.
	 */
	private function wooCommerceOwnsProduct( int $postId ): bool {
		$post = get_post( $postId );
		if ( ! $post || ( $post->post_type ?? '' ) !== 'product' ) {
			return false;
		}

		$active = ( class_exists( '\WC_Product' ) || function_exists( 'wc_get_product' ) ) && ( ! defined( 'QUINREL_TESTS' ) || ! empty( $GLOBALS['mock_woocommerce_active'] ) );
		return (bool) apply_filters( 'quinrel_woocommerce_owns_product_schema', $active, $postId );
	}

	/**
	 * FAQ Schema.
	 */
	private function generateFaqSchema( int $postId ): array {
		$faqsJson = get_post_meta( $postId, '_quinrel_schema_faq_questions', true );
		$faqs     = ! empty( $faqsJson ) ? json_decode( $faqsJson, true ) : array();

		if ( empty( $faqs ) || ! is_array( $faqs ) ) {
			return array();
		}

		$mainEntities = array();
		foreach ( $faqs as $item ) {
			if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
				continue;
			}
			$mainEntities[] = array(
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $item['answer'],
				),
			);
		}

		if ( empty( $mainEntities ) ) {
			return array();
		}

		return array(
			'@type'      => 'FAQPage',
			'@id'        => get_permalink( $postId ) . '#faq',
			'mainEntity' => $mainEntities,
		);
	}

	/**
	 * HowTo Schema.
	 */
	private function generateHowToSchema( int $postId ): array {
		$name = $this->textValue( get_post_meta( $postId, '_quinrel_schema_howto_name', true ), get_the_title( $postId ) );
		$desc = $this->textValue( get_post_meta( $postId, '_quinrel_schema_howto_description', true ), wp_strip_all_tags( get_the_excerpt( $postId ) ) );
		if ( $name === '' ) {
			return array();
		}

		$stepsJson = get_post_meta( $postId, '_quinrel_schema_howto_steps', true );
		$steps     = ! empty( $stepsJson ) ? json_decode( $stepsJson, true ) : array();

		$suppliesText = get_post_meta( $postId, '_quinrel_schema_howto_supplies', true );
		$toolsText    = get_post_meta( $postId, '_quinrel_schema_howto_tools', true );

		$schema = array(
			'@type' => 'HowTo',
			'@id'   => get_permalink( $postId ) . '#howto',
			'name'  => $name,
		);
		$schema = $this->putText( $schema, 'description', $desc );

		if ( ! empty( $steps ) && is_array( $steps ) ) {
			$stepElements = array();
			foreach ( $steps as $idx => $step ) {
				if ( empty( $step['text'] ) ) {
					continue;
				}
				$stepElements[] = array(
					'@type' => 'HowToStep',
					'name'  => isset( $step['name'] ) && $step['name'] !== '' ? $step['name'] : ( 'Step ' . ( $idx + 1 ) ),
					'text'  => $step['text'],
					'url'   => get_permalink( $postId ) . '#step-' . ( $idx + 1 ),
				);
			}
			if ( ! empty( $stepElements ) ) {
				$schema['step'] = $stepElements;
			}
		}

		if ( empty( $schema['step'] ) ) {
			return array();
		}

		$supplies = $this->namedThings( (string) $suppliesText, 'HowToSupply' );
		if ( array() !== $supplies ) {
			$schema['supply'] = $supplies;
		}
		$tools = $this->namedThings( (string) $toolsText, 'HowToTool' );
		if ( array() !== $tools ) {
			$schema['tool'] = $tools;
		}

		return $schema;
	}

	/**
	 * LocalBusiness Schema.
	 */
	private function generateLocalBusinessSchema( int $postId ): array {
		$name      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_localbusiness_name', true ), get_bloginfo( 'name' ) );
		$telephone = $this->textValue( get_post_meta( $postId, '_quinrel_schema_localbusiness_telephone', true ) );
		$street    = $this->textValue( get_post_meta( $postId, '_quinrel_schema_localbusiness_street', true ) );
		$city      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_localbusiness_city', true ) );
		$postal    = $this->textValue( get_post_meta( $postId, '_quinrel_schema_localbusiness_postal', true ) );
		$country   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_localbusiness_country', true ) );

		if ( $name === '' || ( $street === '' && $city === '' ) ) {
			return array();
		}

		$schema = array(
			'@type' => 'LocalBusiness',
			'@id'   => get_permalink( $postId ) . '#localbusiness',
			'name'  => $name,
			'url'   => get_permalink( $postId ),
		);
		$schema = $this->putText( $schema, 'telephone', $telephone );
		$image  = $this->thumbnailUrl( $postId );
		if ( $image !== '' ) {
			$schema['image'] = $image;
		}

		$address           = array( '@type' => 'PostalAddress' );
		$address           = $this->putText( $address, 'streetAddress', $street );
		$address           = $this->putText( $address, 'addressLocality', $city );
		$address           = $this->putText( $address, 'postalCode', $postal );
		$address           = $this->putText( $address, 'addressCountry', $country );
		$schema['address'] = $address;

		return $schema;
	}

	/**
	 * Recipe Schema.
	 */
	private function generateRecipeSchema( int $postId ): array {
		$name  = $this->textValue( get_post_meta( $postId, '_quinrel_schema_recipe_name', true ), get_the_title( $postId ) );
		$desc  = $this->textValue( get_post_meta( $postId, '_quinrel_schema_recipe_description', true ), wp_strip_all_tags( get_the_excerpt( $postId ) ) );
		$image = $this->thumbnailUrl( $postId );
		if ( $name === '' || $image === '' ) {
			return array();
		}

		$ingredientsText = get_post_meta( $postId, '_quinrel_schema_recipe_ingredients', true );
		$ingredients     = array();
		if ( ! empty( $ingredientsText ) ) {
			// Can be JSON or comma-separated
			$decoded = json_decode( $ingredientsText, true );
			if ( is_array( $decoded ) ) {
				$ingredients = $decoded;
			} else {
				$ingredients = array_map( 'trim', explode( ',', $ingredientsText ) );
			}
		}
		$ingredients = array_values(
			array_filter(
				$ingredients,
				static function ( $item ): bool {
					return is_string( $item ) && trim( $item ) !== '';
				}
			)
		);

		$instructionsJson = get_post_meta( $postId, '_quinrel_schema_recipe_instructions', true );
		$instructions     = ! empty( $instructionsJson ) ? json_decode( $instructionsJson, true ) : array();

		$prepTimeRaw = get_post_meta( $postId, '_quinrel_schema_recipe_prep_time', true );
		$cookTimeRaw = get_post_meta( $postId, '_quinrel_schema_recipe_cook_time', true );
		$calories    = get_post_meta( $postId, '_quinrel_schema_recipe_calories', true );

		$schema = array(
			'@type' => 'Recipe',
			'@id'   => get_permalink( $postId ) . '#recipe',
			'name'  => $name,
			'image' => $image,
			'url'   => get_permalink( $postId ),
		);
		$schema = $this->putText( $schema, 'description', $desc );

		if ( ! empty( $ingredients ) ) {
			$schema['recipeIngredient'] = $ingredients;
		}

		if ( ! empty( $instructions ) && is_array( $instructions ) ) {
			$instructionElements = array();
			foreach ( $instructions as $idx => $step ) {
				if ( empty( $step['text'] ) ) {
					continue;
				}
				$instructionElements[] = array(
					'@type' => 'HowToStep',
					'name'  => isset( $step['name'] ) && $step['name'] !== '' ? $step['name'] : ( 'Step ' . ( $idx + 1 ) ),
					'text'  => $step['text'],
				);
			}
			if ( ! empty( $instructionElements ) ) {
				$schema['recipeInstructions'] = $instructionElements;
			}
		}

		$prepIso = $this->convertToIsoDuration( $prepTimeRaw );
		if ( $prepIso ) {
			$schema['prepTime'] = $prepIso;
		}

		$cookIso = $this->convertToIsoDuration( $cookTimeRaw );
		if ( $cookIso ) {
			$schema['cookTime'] = $cookIso;
		}

		if ( ! empty( $calories ) ) {
			$schema['nutrition'] = array(
				'@type'    => 'NutritionInformation',
				'calories' => $calories . ' calories',
			);
		}

		return $schema;
	}

	/**
	 * Event Schema.
	 */
	private function generateEventSchema( int $postId ): array {
		$name          = $this->textValue( get_post_meta( $postId, '_quinrel_schema_event_name', true ), get_the_title( $postId ) );
		$startDate     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_event_start_date', true ) );
		$endDate       = $this->textValue( get_post_meta( $postId, '_quinrel_schema_event_end_date', true ) );
		$venue         = $this->textValue( get_post_meta( $postId, '_quinrel_schema_event_venue', true ), 'Online' );
		$address       = $this->textValue( get_post_meta( $postId, '_quinrel_schema_event_address', true ) );
		$performerName = $this->textValue( get_post_meta( $postId, '_quinrel_schema_event_performer', true ) );
		$price         = $this->textValue( get_post_meta( $postId, '_quinrel_schema_event_price', true ) );
		$currency      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_event_currency', true ), 'USD' );

		if ( $name === '' || $startDate === '' ) {
			return array();
		}

		$schema = array(
			'@type'               => 'Event',
			'@id'                 => get_permalink( $postId ) . '#event',
			'name'                => $name,
			'url'                 => get_permalink( $postId ),
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => $address === '' ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
		);

		$image = $this->thumbnailUrl( $postId );
		if ( $image !== '' ) {
			$schema['image'] = $image;
		}

		if ( ! empty( $startDate ) ) {
			// Convert to ISO 8601
			$schema['startDate'] = gmdate( 'c', strtotime( $startDate ) );
		}

		if ( ! empty( $endDate ) ) {
			$schema['endDate'] = gmdate( 'c', strtotime( $endDate ) );
		}

		if ( ! empty( $address ) ) {
			$schema['location'] = array(
				'@type'   => 'Place',
				'name'    => $venue,
				'address' => array(
					'@type'         => 'PostalAddress',
					'streetAddress' => $address,
				),
			);
		} else {
			$schema['location'] = array(
				'@type' => 'VirtualLocation',
				'url'   => get_permalink( $postId ),
			);
		}

		if ( ! empty( $performerName ) ) {
			$schema['performer'] = array(
				'@type' => 'Person',
				'name'  => $performerName,
			);
		}

		if ( ! empty( $price ) ) {
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => floatval( $price ),
				'priceCurrency' => $currency,
				'availability'  => 'https://schema.org/InStock',
				'url'           => get_permalink( $postId ),
			);
		}

		return $schema;
	}

	/**
	 * Podcast Episode Schema.
	 */
	private function generatePodcastSchema( int $postId ): array {
		$title      = $this->textValue( get_post_meta( $postId, '_quinrel_podcast_title', true ), get_the_title( $postId ) );
		$desc       = $this->textValue( get_post_meta( $postId, '_quinrel_podcast_description', true ), wp_strip_all_tags( get_the_excerpt( $postId ) ) );
		$epNumber   = $this->textValue( get_post_meta( $postId, '_quinrel_podcast_episode_number', true ) );
		$seasonNum  = $this->textValue( get_post_meta( $postId, '_quinrel_podcast_season_number', true ) );
		$epType     = $this->textValue( get_post_meta( $postId, '_quinrel_podcast_episode_type', true ), 'full' );
		$seriesName = $this->textValue( get_post_meta( $postId, '_quinrel_podcast_series_name', true ), get_bloginfo( 'name' ) );
		$seriesUrl  = $this->textValue( get_post_meta( $postId, '_quinrel_podcast_series_url', true ), home_url( '/' ) );
		$duration   = $this->textValue( get_post_meta( $postId, '_quinrel_podcast_duration', true ) );
		$transcript = $this->textValue( get_post_meta( $postId, '_quinrel_podcast_transcript', true ) );

		if ( $title === '' ) {
			return array();
		}

		$schema = array(
			'@type'         => 'PodcastEpisode',
			'@id'           => get_permalink( $postId ) . '#podcastepisode',
			'url'           => get_permalink( $postId ),
			'name'          => $title,
			'datePublished' => get_the_date( 'c', $postId ),
		);
		$schema = $this->putText( $schema, 'description', $desc );

		if ( $epNumber !== '' ) {
			$schema['episodeNumber'] = is_numeric( $epNumber ) ? intval( $epNumber ) : $epNumber;
		}
		if ( $seasonNum !== '' ) {
			$schema['seasonNumber'] = is_numeric( $seasonNum ) ? intval( $seasonNum ) : $seasonNum;
		}
		if ( in_array( $epType, array( 'full', 'trailer', 'bonus' ), true ) ) {
			$schema['episodeType'] = $epType;
		}

		if ( $seriesName !== '' ) {
			$schema['partOfSeries'] = array(
				'@type' => 'PodcastSeries',
				'name'  => $seriesName,
				'url'   => $seriesUrl,
			);
		}

		$audioObject = AudioExtractor::extractAudioObject( $postId );
		if ( ! empty( $audioObject ) ) {
			if ( $duration !== '' ) {
				$isoDuration = $this->convertToIsoDuration( $duration );
				if ( $isoDuration !== '' ) {
					$audioObject['duration'] = $isoDuration;
					$schema['timeRequired']  = $isoDuration;
				}
			}
			if ( $transcript !== '' ) {
				$audioObject['transcript'] = $transcript;
			}
			$schema['associatedMedia'] = $audioObject;
		} elseif ( $duration !== '' ) {
			$isoDuration = $this->convertToIsoDuration( $duration );
			if ( $isoDuration !== '' ) {
				$schema['timeRequired'] = $isoDuration;
			}
		}

		$image = $this->thumbnailUrl( $postId );
		if ( $image !== '' ) {
			$schema['image'] = $image;
		}

		return $schema;
	}

	/**
	 * Course Schema.
	 */
	private function generateCourseSchema( int $postId ): array {
		$name       = $this->textValue( get_post_meta( $postId, '_quinrel_schema_course_name', true ), get_the_title( $postId ) );
		$desc       = $this->textValue( get_post_meta( $postId, '_quinrel_schema_course_description', true ), wp_strip_all_tags( get_the_excerpt( $postId ) ) );
		$courseCode = $this->textValue( get_post_meta( $postId, '_quinrel_schema_course_code', true ) );
		$level      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_course_level', true ) );
		$provider   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_course_provider', true ), get_bloginfo( 'name' ) );
		$price      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_course_price', true ) );
		$currency   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_course_currency', true ), 'USD' );

		if ( $name === '' ) {
			return array();
		}

		$schema = array(
			'@type'    => 'Course',
			'@id'      => get_permalink( $postId ) . '#course',
			'name'     => $name,
			'url'      => get_permalink( $postId ),
			'provider' => array(
				'@type'  => 'Organization',
				'name'   => $provider,
				'sameAs' => home_url( '/' ),
			),
		);
		$schema = $this->putText( $schema, 'description', $desc );
		$schema = $this->putText( $schema, 'courseCode', $courseCode );
		$schema = $this->putText( $schema, 'educationalLevel', $level );

		$image = $this->thumbnailUrl( $postId );
		if ( $image !== '' ) {
			$schema['image'] = $image;
		}

		if ( $price !== '' ) {
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => floatval( $price ),
				'priceCurrency' => $currency,
				'category'      => floatval( $price ) > 0 ? 'Paid' : 'Free',
			);
		}

		return $schema;
	}

	/**
	 * JobPosting Schema.
	 */
	private function generateJobPostingSchema( int $postId ): array {
		$title        = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_title', true ), get_the_title( $postId ) );
		$desc         = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_description', true ), wp_strip_all_tags( get_the_excerpt( $postId ) ) );
		$company      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_company', true ), get_bloginfo( 'name' ) );
		$companyUrl   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_company_url', true ), home_url( '/' ) );
		$empType      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_employment_type', true ), 'FULL_TIME' );
		$validThrough = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_valid_through', true ) );
		$locType      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_location_type', true ) );
		$street       = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_street', true ) );
		$city         = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_city', true ) );
		$postal       = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_postal', true ) );
		$country      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_country', true ) );
		$salary       = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_salary', true ) );
		$salaryCurr   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_salary_currency', true ), 'USD' );
		$salaryUnit   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_job_salary_unit', true ), 'YEAR' );

		if ( $title === '' ) {
			return array();
		}

		if ( $desc === '' ) {
			$rawContent = get_post_field( 'post_content', $postId );
			$desc       = is_string( $rawContent ) ? wp_strip_all_tags( $rawContent ) : '';
		}

		$schema = array(
			'@type'              => 'JobPosting',
			'@id'                => get_permalink( $postId ) . '#jobposting',
			'title'              => $title,
			'description'        => $desc,
			'datePosted'         => get_the_date( 'c', $postId ),
			'employmentType'     => $empType,
			'hiringOrganization' => array(
				'@type'  => 'Organization',
				'name'   => $company,
				'sameAs' => $companyUrl,
			),
		);

		if ( $validThrough !== '' ) {
			$schema['validThrough'] = gmdate( 'c', strtotime( $validThrough ) );
		} else {
			$schema['validThrough'] = gmdate( 'c', strtotime( get_the_date( 'Y-m-d', $postId ) . ' +90 days' ) );
		}

		if ( in_array( strtoupper( $locType ), array( 'TELECOMMUTE', 'REMOTE' ), true ) ) {
			$schema['jobLocationType']               = 'TELECOMMUTE';
			$schema['applicantLocationRequirements'] = array(
				'@type' => 'Country',
				'name'  => $country !== '' ? $country : 'Worldwide',
			);
		} else {
			$address = array( '@type' => 'PostalAddress' );
			$address = $this->putText( $address, 'streetAddress', $street );
			$address = $this->putText( $address, 'addressLocality', $city );
			$address = $this->putText( $address, 'postalCode', $postal );
			$address = $this->putText( $address, 'addressCountry', $country );

			$schema['jobLocation'] = array(
				'@type'   => 'Place',
				'address' => $address,
			);
		}

		if ( $salary !== '' ) {
			$schema['baseSalary'] = array(
				'@type'    => 'MonetaryAmount',
				'currency' => $salaryCurr,
				'value'    => array(
					'@type'    => 'QuantitativeValue',
					'value'    => floatval( $salary ),
					'unitText' => $salaryUnit,
				),
			);
		}

		return $schema;
	}

	/**
	 * Book Schema.
	 */
	private function generateBookSchema( int $postId ): array {
		$name      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_book_name', true ), get_the_title( $postId ) );
		$author    = $this->textValue( get_post_meta( $postId, '_quinrel_schema_book_author', true ) );
		$isbn      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_book_isbn', true ) );
		$format    = $this->textValue( get_post_meta( $postId, '_quinrel_schema_book_format', true ), 'Paperback' );
		$datePub   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_book_date_published', true ), get_the_date( 'Y-m-d', $postId ) );
		$pages     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_book_pages', true ) );
		$publisher = $this->textValue( get_post_meta( $postId, '_quinrel_schema_book_publisher', true ) );

		if ( $name === '' ) {
			return array();
		}

		if ( $author === '' ) {
			$authorId = (int) get_post_field( 'post_author', $postId );
			$author   = get_the_author_meta( 'display_name', $authorId );
		}

		$formatMap = array(
			'hardcover' => 'https://schema.org/Hardcover',
			'paperback' => 'https://schema.org/Paperback',
			'ebook'     => 'https://schema.org/EBook',
			'audiobook' => 'https://schema.org/AudiobookFormat',
		);
		$formatUrl = $formatMap[ strtolower( $format ) ] ?? 'https://schema.org/Paperback';

		$schema = array(
			'@type'         => 'Book',
			'@id'           => get_permalink( $postId ) . '#book',
			'name'          => $name,
			'url'           => get_permalink( $postId ),
			'bookFormat'    => $formatUrl,
			'datePublished' => $datePub,
		);

		if ( $author !== '' ) {
			$schema['author'] = array(
				'@type' => 'Person',
				'name'  => $author,
			);
		}

		$schema = $this->putText( $schema, 'isbn', $isbn );

		if ( $pages !== '' && is_numeric( $pages ) ) {
			$schema['numberOfPages'] = intval( $pages );
		}

		if ( $publisher !== '' ) {
			$schema['publisher'] = array(
				'@type' => 'Organization',
				'name'  => $publisher,
			);
		}

		$image = $this->thumbnailUrl( $postId );
		if ( $image !== '' ) {
			$schema['image'] = $image;
		}

		return $schema;
	}

	/**
	 * SoftwareApplication Schema.
	 */
	private function generateSoftwareSchema( int $postId ): array {
		$name     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_software_name', true ), get_the_title( $postId ) );
		$os       = $this->textValue( get_post_meta( $postId, '_quinrel_schema_software_os', true ), 'Web' );
		$category = $this->textValue( get_post_meta( $postId, '_quinrel_schema_software_category', true ), 'BusinessApplication' );
		$price    = $this->textValue( get_post_meta( $postId, '_quinrel_schema_software_price', true ) );
		$currency = $this->textValue( get_post_meta( $postId, '_quinrel_schema_software_currency', true ), 'USD' );
		$rating   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_software_rating', true ) );

		if ( $name === '' ) {
			return array();
		}

		$schema = array(
			'@type'               => 'SoftwareApplication',
			'@id'                 => get_permalink( $postId ) . '#software',
			'name'                => $name,
			'operatingSystem'     => $os,
			'applicationCategory' => $category,
			'url'                 => get_permalink( $postId ),
		);

		$image = $this->thumbnailUrl( $postId );
		if ( $image !== '' ) {
			$schema['image'] = $image;
		}

		if ( $price !== '' ) {
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => floatval( $price ),
				'priceCurrency' => $currency,
			);
		}

		if ( $rating !== '' ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => floatval( $rating ),
				'bestRating'  => 5,
				'ratingCount' => 1,
			);
		}

		return $schema;
	}

	/**
	 * Restaurant Schema (subtype of LocalBusiness).
	 */
	private function generateRestaurantSchema( int $postId ): array {
		$name         = $this->textValue( get_post_meta( $postId, '_quinrel_schema_restaurant_name', true ), get_the_title( $postId ) );
		$cuisine      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_restaurant_cuisine', true ) );
		$priceRange   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_restaurant_price_range', true ), '$$' );
		$telephone    = $this->textValue( get_post_meta( $postId, '_quinrel_schema_restaurant_telephone', true ) );
		$street       = $this->textValue( get_post_meta( $postId, '_quinrel_schema_restaurant_street', true ) );
		$city         = $this->textValue( get_post_meta( $postId, '_quinrel_schema_restaurant_city', true ) );
		$postal       = $this->textValue( get_post_meta( $postId, '_quinrel_schema_restaurant_postal', true ) );
		$country      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_restaurant_country', true ) );
		$menuUrl      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_restaurant_menu_url', true ) );
		$reservations = $this->textValue( get_post_meta( $postId, '_quinrel_schema_restaurant_reservations', true ) );

		if ( $name === '' ) {
			return array();
		}

		$schema = array(
			'@type'      => 'Restaurant',
			'@id'        => get_permalink( $postId ) . '#restaurant',
			'name'       => $name,
			'priceRange' => $priceRange,
			'url'        => get_permalink( $postId ),
		);
		$schema = $this->putText( $schema, 'telephone', $telephone );
		$schema = $this->putText( $schema, 'servesCuisine', $cuisine );

		if ( $menuUrl !== '' ) {
			$schema['hasMenu'] = $menuUrl;
		}
		if ( $reservations !== '' ) {
			$schema['acceptsReservations'] = in_array( strtolower( $reservations ), array( 'yes', 'true', '1' ), true ) ? 'True' : 'False';
		}

		$image = $this->thumbnailUrl( $postId );
		if ( $image !== '' ) {
			$schema['image'] = $image;
		}

		$address = array( '@type' => 'PostalAddress' );
		$address = $this->putText( $address, 'streetAddress', $street );
		$address = $this->putText( $address, 'addressLocality', $city );
		$address = $this->putText( $address, 'postalCode', $postal );
		$address = $this->putText( $address, 'addressCountry', $country );

		if ( count( $address ) > 1 ) {
			$schema['address'] = $address;
		}

		return $schema;
	}

	/**
	 * Service Schema.
	 */
	private function generateServiceSchema( int $postId ): array {
		$name     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_service_name', true ), get_the_title( $postId ) );
		$desc     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_service_description', true ), wp_strip_all_tags( get_the_excerpt( $postId ) ) );
		$type     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_service_type', true ) );
		$provider = $this->textValue( get_post_meta( $postId, '_quinrel_schema_service_provider', true ), get_bloginfo( 'name' ) );
		$area     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_service_area', true ) );
		$terms    = $this->textValue( get_post_meta( $postId, '_quinrel_schema_service_terms', true ) );
		$price    = $this->textValue( get_post_meta( $postId, '_quinrel_schema_service_price', true ) );
		$currency = $this->textValue( get_post_meta( $postId, '_quinrel_schema_service_currency', true ), 'USD' );

		if ( $name === '' ) {
			return array();
		}

		$schema = array(
			'@type'    => 'Service',
			'@id'      => get_permalink( $postId ) . '#service',
			'name'     => $name,
			'provider' => array(
				'@type' => 'Organization',
				'name'  => $provider,
			),
		);
		$schema = $this->putText( $schema, 'description', $desc );
		$schema = $this->putText( $schema, 'serviceType', $type );
		$schema = $this->putText( $schema, 'areaServed', $area );
		$schema = $this->putText( $schema, 'termsOfService', $terms );

		if ( $price !== '' ) {
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => floatval( $price ),
				'priceCurrency' => $currency,
			);
		}

		return $schema;
	}

	/**
	 * Review Schema.
	 */
	private function generateReviewSchema( int $postId ): array {
		$itemName = $this->textValue( get_post_meta( $postId, '_quinrel_schema_review_item_name', true ), get_the_title( $postId ) );
		$itemType = $this->textValue( get_post_meta( $postId, '_quinrel_schema_review_item_type', true ), 'Thing' );
		$rating   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_review_rating', true ), '5' );
		$body     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_review_body', true ), wp_strip_all_tags( get_the_excerpt( $postId ) ) );
		$author   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_review_author', true ) );

		if ( $itemName === '' ) {
			return array();
		}

		if ( $author === '' ) {
			$authorId = (int) get_post_field( 'post_author', $postId );
			$author   = get_the_author_meta( 'display_name', $authorId );
		}

		$schema = array(
			'@type'         => 'Review',
			'@id'           => get_permalink( $postId ) . '#review',
			'itemReviewed'  => array(
				'@type' => $itemType,
				'name'  => $itemName,
			),
			'reviewRating'  => array(
				'@type'       => 'Rating',
				'ratingValue' => floatval( $rating ),
				'bestRating'  => 5,
				'worstRating' => 1,
			),
			'author'        => array(
				'@type' => 'Person',
				'name'  => $author,
			),
			'datePublished' => get_the_date( 'c', $postId ),
		);
		$schema = $this->putText( $schema, 'reviewBody', $body );

		return $schema;
	}

	/**
	 * Movie Schema.
	 */
	private function generateMovieSchema( int $postId ): array {
		$name      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_movie_name', true ), get_the_title( $postId ) );
		$director  = $this->textValue( get_post_meta( $postId, '_quinrel_schema_movie_director', true ) );
		$actorsCsv = $this->textValue( get_post_meta( $postId, '_quinrel_schema_movie_actors', true ) );
		$date      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_movie_date', true ) );
		$duration  = $this->textValue( get_post_meta( $postId, '_quinrel_schema_movie_duration', true ) );

		if ( $name === '' ) {
			return array();
		}

		$schema = array(
			'@type' => 'Movie',
			'@id'   => get_permalink( $postId ) . '#movie',
			'name'  => $name,
			'url'   => get_permalink( $postId ),
		);
		$schema = $this->putText( $schema, 'dateCreated', $date );

		if ( $director !== '' ) {
			$schema['director'] = array(
				'@type' => 'Person',
				'name'  => $director,
			);
		}

		if ( $actorsCsv !== '' ) {
			$schema['actor'] = $this->namedThings( $actorsCsv, 'Person' );
		}

		if ( $duration !== '' ) {
			$iso = $this->convertToIsoDuration( $duration );
			if ( $iso !== '' ) {
				$schema['duration'] = $iso;
			}
		}

		$image = $this->thumbnailUrl( $postId );
		if ( $image !== '' ) {
			$schema['image'] = $image;
		}

		return $schema;
	}

	/**
	 * MusicRecording Schema.
	 */
	private function generateMusicSchema( int $postId ): array {
		$name     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_music_name', true ), get_the_title( $postId ) );
		$artist   = $this->textValue( get_post_meta( $postId, '_quinrel_schema_music_artist', true ) );
		$album    = $this->textValue( get_post_meta( $postId, '_quinrel_schema_music_album', true ) );
		$duration = $this->textValue( get_post_meta( $postId, '_quinrel_schema_music_duration', true ) );

		if ( $name === '' ) {
			return array();
		}

		$schema = array(
			'@type' => 'MusicRecording',
			'@id'   => get_permalink( $postId ) . '#music',
			'name'  => $name,
			'url'   => get_permalink( $postId ),
		);

		if ( $artist !== '' ) {
			$schema['byArtist'] = array(
				'@type' => 'MusicGroup',
				'name'  => $artist,
			);
		}

		if ( $album !== '' ) {
			$schema['inAlbum'] = array(
				'@type' => 'MusicAlbum',
				'name'  => $album,
			);
		}

		if ( $duration !== '' ) {
			$iso = $this->convertToIsoDuration( $duration );
			if ( $iso !== '' ) {
				$schema['duration'] = $iso;
			}
		}

		$image = $this->thumbnailUrl( $postId );
		if ( $image !== '' ) {
			$schema['image'] = $image;
		}

		return $schema;
	}

	/**
	 * Dataset Schema.
	 */
	private function generateDatasetSchema( int $postId ): array {
		$name        = $this->textValue( get_post_meta( $postId, '_quinrel_schema_dataset_name', true ), get_the_title( $postId ) );
		$desc        = $this->textValue( get_post_meta( $postId, '_quinrel_schema_dataset_description', true ), wp_strip_all_tags( get_the_excerpt( $postId ) ) );
		$license     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_dataset_license', true ) );
		$downloadUrl = $this->textValue( get_post_meta( $postId, '_quinrel_schema_dataset_download_url', true ) );
		$format      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_dataset_format', true ), 'text/csv' );
		$creator     = $this->textValue( get_post_meta( $postId, '_quinrel_schema_dataset_creator', true ), get_bloginfo( 'name' ) );

		if ( $name === '' || $desc === '' ) {
			return array();
		}

		$schema = array(
			'@type'       => 'Dataset',
			'@id'         => get_permalink( $postId ) . '#dataset',
			'name'        => $name,
			'description' => $desc,
			'url'         => get_permalink( $postId ),
			'creator'     => array(
				'@type' => 'Organization',
				'name'  => $creator,
			),
		);
		$schema = $this->putText( $schema, 'license', $license );

		if ( $downloadUrl !== '' ) {
			$schema['distribution'] = array(
				'@type'          => 'DataDownload',
				'contentUrl'     => $downloadUrl,
				'encodingFormat' => $format,
			);
		}

		return $schema;
	}

	/**
	 * Person Schema.
	 */
	private function generatePersonSchema( int $postId ): array {
		$name      = $this->textValue( get_post_meta( $postId, '_quinrel_schema_person_name', true ), get_the_title( $postId ) );
		$jobTitle  = $this->textValue( get_post_meta( $postId, '_quinrel_schema_person_job_title', true ) );
		$worksFor  = $this->textValue( get_post_meta( $postId, '_quinrel_schema_person_works_for', true ) );
		$sameAsRaw = $this->textValue( get_post_meta( $postId, '_quinrel_schema_person_same_as', true ) );
		$url       = $this->textValue( get_post_meta( $postId, '_quinrel_schema_person_url', true ), get_permalink( $postId ) );

		if ( $name === '' ) {
			return array();
		}

		$schema = array(
			'@type' => 'Person',
			'@id'   => get_permalink( $postId ) . '#person',
			'name'  => $name,
			'url'   => $url,
		);
		$schema = $this->putText( $schema, 'jobTitle', $jobTitle );

		if ( $worksFor !== '' ) {
			$schema['worksFor'] = array(
				'@type' => 'Organization',
				'name'  => $worksFor,
			);
		}

		if ( $sameAsRaw !== '' ) {
			$urls = array_values(
				array_filter(
					array_map( 'trim', explode( ',', $sameAsRaw ) ),
					static function ( $u ): bool {
						return filter_var( $u, FILTER_VALIDATE_URL ) !== false;
					}
				)
			);
			if ( ! empty( $urls ) ) {
				$schema['sameAs'] = $urls;
			}
		}

		$image = $this->thumbnailUrl( $postId );
		if ( $image !== '' ) {
			$schema['image'] = $image;
		}

		return $schema;
	}

	/**
	 * First non-empty trimmed string.
	 */
	private function textValue( $primary, string $fallback = '' ): string {
		foreach ( array( $primary, $fallback ) as $value ) {
			if ( is_string( $value ) || is_numeric( $value ) ) {
				$text = trim( (string) $value );
				if ( $text !== '' ) {
					return $text;
				}
			}
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	private function putText( array $schema, string $key, string $value ): array {
		if ( $value !== '' ) {
			$schema[ $key ] = $value;
		}
		return $schema;
	}

	private function thumbnailUrl( int $postId ): string {
		if ( ! has_post_thumbnail( $postId ) ) {
			return '';
		}
		$url = get_the_post_thumbnail_url( $postId, 'full' );
		return is_string( $url ) ? trim( $url ) : '';
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function namedThings( string $csv, string $type ): array {
		$items = array();
		foreach ( array_map( 'trim', explode( ',', $csv ) ) as $name ) {
			if ( $name === '' ) {
				continue;
			}
			$items[] = array(
				'@type' => $type,
				'name'  => $name,
			);
		}
		return $items;
	}

	/**
	 * Converts a duration representation into ISO 8601 duration format.
	 */
	private function convertToIsoDuration( string $time ): string {
		$time = trim( $time );
		if ( empty( $time ) ) {
			return '';
		}
		if ( strpos( $time, 'P' ) === 0 ) {
			return $time;
		}

		$hours   = 0;
		$minutes = 0;

		if ( preg_match( '/(\d+)\s*(?:hour|hr|h)/i', $time, $m ) ) {
			$hours = intval( $m[1] );
		}
		if ( preg_match( '/(\d+)\s*(?:minute|min|m)/i', $time, $m ) ) {
			$minutes = intval( $m[1] );
		}

		if ( $hours === 0 && $minutes === 0 && is_numeric( $time ) ) {
			$minutes = intval( $time );
		}

		$duration = 'PT';
		if ( $hours > 0 ) {
			$duration .= $hours . 'H';
		}
		if ( $minutes > 0 || $hours === 0 ) {
			$duration .= $minutes . 'M';
		}
		return $duration;
	}

	/**
	 * Merge user-defined visual tree properties dynamically.
	 */
	private function mergeCustomProperties( int $postId, array $schema ): array {
		$parsed = $this->parsedCustomJson( $postId );
		if ( ! is_array( $parsed ) || 'properties' !== ( $parsed['kind'] ?? '' ) ) {
			return $schema;
		}

		foreach ( $parsed['properties'] as $key => $value ) {
			$schema[ $key ] = $value;
		}

		return $schema;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function parsedCustomJson( int $postId ): ?array {
		$raw = get_post_meta( $postId, '_quinrel_custom_schema_properties', true );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return null;
		}

		$parsed = JsonLd::parseCustomJson( $raw );
		if ( is_wp_error( $parsed ) || 'empty' === ( $parsed['kind'] ?? '' ) ) {
			return null;
		}

		return $parsed;
	}

	private function customType( int $postId ): string {
		$type = get_post_meta( $postId, '_quinrel_schema_custom_type', true );
		if ( ! JsonLd::isSchemaType( $type ) ) {
			return 'Thing';
		}

		return $type;
	}
}
