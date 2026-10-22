<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Schema\Contracts\SchemaTypeInterface;
use Quinrel\WordPress\Modules\Schema\SchemaGenerator;
use Quinrel\WordPress\Modules\Schema\Types\CustomSchema;

class SchemaTypesTest extends TestCase
{
    private CustomSchema $customSchema;
    private SchemaGenerator $schemaGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customSchema = new CustomSchema();
        $this->schemaGenerator = new SchemaGenerator();

        global $mock_post_meta, $mock_posts, $mock_current_post_id;
        $mock_post_meta = [];
        $mock_posts = [];
        $mock_current_post_id = 0;
    }

    public function testCourseSchema(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 601;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Advanced Machine Learning Specialization',
            'post_type' => 'post',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'course',
            '_quinrel_schema_course_name' => 'Advanced Machine Learning Specialization',
            '_quinrel_schema_course_description' => 'Master deep learning and transformers.',
            '_quinrel_schema_course_code' => 'CS-480',
            '_quinrel_schema_course_level' => 'Advanced',
            '_quinrel_schema_course_provider' => 'Quinrel Academy',
            '_quinrel_schema_course_price' => '199',
            '_quinrel_schema_course_currency' => 'USD',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertSame('Course', $schema['@type']);
        $this->assertSame('Advanced Machine Learning Specialization', $schema['name']);
        $this->assertSame('CS-480', $schema['courseCode']);
        $this->assertSame('Advanced', $schema['educationalLevel']);
        $this->assertSame('Organization', $schema['provider']['@type']);
        $this->assertSame('Quinrel Academy', $schema['provider']['name']);
        $this->assertSame(199.0, $schema['offers']['price']);
        $this->assertSame('USD', $schema['offers']['priceCurrency']);
        $this->assertSame('Paid', $schema['offers']['category']);
    }

    public function testJobPostingSchema(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 602;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Senior Infrastructure Architect',
            'post_type' => 'post',
            'post_date' => '2026-10-22 12:00:00',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'jobposting',
            '_quinrel_schema_job_title' => 'Senior Infrastructure Architect',
            '_quinrel_schema_job_description' => 'Lead global edge cloud platform architecture.',
            '_quinrel_schema_job_company' => 'Acme Cloud',
            '_quinrel_schema_job_company_url' => 'https://acme.example.com',
            '_quinrel_schema_job_employment_type' => 'FULL_TIME',
            '_quinrel_schema_job_location_type' => 'REMOTE',
            '_quinrel_schema_job_country' => 'United States',
            '_quinrel_schema_job_salary' => '180000',
            '_quinrel_schema_job_salary_currency' => 'USD',
            '_quinrel_schema_job_salary_unit' => 'YEAR',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertSame('JobPosting', $schema['@type']);
        $this->assertSame('Senior Infrastructure Architect', $schema['title']);
        $this->assertSame('FULL_TIME', $schema['employmentType']);
        $this->assertSame('Acme Cloud', $schema['hiringOrganization']['name']);
        $this->assertSame('TELECOMMUTE', $schema['jobLocationType']);
        $this->assertSame('United States', $schema['applicantLocationRequirements']['name']);
        $this->assertSame(180000.0, $schema['baseSalary']['value']['value']);
        $this->assertSame('YEAR', $schema['baseSalary']['value']['unitText']);
    }

    public function testBookSchema(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 603;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Clean Code Principles',
            'post_type' => 'post',
            'post_date' => '2026-01-15 00:00:00',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'book',
            '_quinrel_schema_book_name' => 'Clean Code Principles',
            '_quinrel_schema_book_author' => 'Robert Developer',
            '_quinrel_schema_book_isbn' => '978-0132350884',
            '_quinrel_schema_book_format' => 'hardcover',
            '_quinrel_schema_book_pages' => '464',
            '_quinrel_schema_book_publisher' => 'Prentice Hall',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertSame('Book', $schema['@type']);
        $this->assertSame('Clean Code Principles', $schema['name']);
        $this->assertSame('Robert Developer', $schema['author']['name']);
        $this->assertSame('https://schema.org/Hardcover', $schema['bookFormat']);
        $this->assertSame('978-0132350884', $schema['isbn']);
        $this->assertSame(464, $schema['numberOfPages']);
        $this->assertSame('Prentice Hall', $schema['publisher']['name']);
    }

    public function testSoftwareApplicationSchema(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 604;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'CodeFlow IDE',
            'post_type' => 'post',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'software',
            '_quinrel_schema_software_name' => 'CodeFlow IDE',
            '_quinrel_schema_software_os' => 'macOS, Windows, Linux',
            '_quinrel_schema_software_category' => 'DeveloperApplication',
            '_quinrel_schema_software_price' => '0',
            '_quinrel_schema_software_currency' => 'USD',
            '_quinrel_schema_software_rating' => '4.9',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertSame('SoftwareApplication', $schema['@type']);
        $this->assertSame('CodeFlow IDE', $schema['name']);
        $this->assertSame('macOS, Windows, Linux', $schema['operatingSystem']);
        $this->assertSame('DeveloperApplication', $schema['applicationCategory']);
        $this->assertSame(0.0, $schema['offers']['price']);
        $this->assertSame(4.9, $schema['aggregateRating']['ratingValue']);
    }

    public function testRestaurantSchema(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 605;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Trattoria Roma',
            'post_type' => 'post',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'restaurant',
            '_quinrel_schema_restaurant_name' => 'Trattoria Roma',
            '_quinrel_schema_restaurant_cuisine' => 'Italian',
            '_quinrel_schema_restaurant_price_range' => '$$$',
            '_quinrel_schema_restaurant_telephone' => '+1-555-0199',
            '_quinrel_schema_restaurant_street' => '123 Piazza Way',
            '_quinrel_schema_restaurant_city' => 'San Francisco',
            '_quinrel_schema_restaurant_country' => 'US',
            '_quinrel_schema_restaurant_menu_url' => 'https://trattoria.example.com/menu',
            '_quinrel_schema_restaurant_reservations' => 'yes',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertSame('Restaurant', $schema['@type']);
        $this->assertSame('Trattoria Roma', $schema['name']);
        $this->assertSame('Italian', $schema['servesCuisine']);
        $this->assertSame('$$$', $schema['priceRange']);
        $this->assertSame('+1-555-0199', $schema['telephone']);
        $this->assertSame('https://trattoria.example.com/menu', $schema['hasMenu']);
        $this->assertSame('True', $schema['acceptsReservations']);
        $this->assertSame('123 Piazza Way', $schema['address']['streetAddress']);
        $this->assertSame('San Francisco', $schema['address']['addressLocality']);
    }

    public function testServiceSchema(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 606;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Enterprise SEO Auditing',
            'post_type' => 'post',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'service',
            '_quinrel_schema_service_name' => 'Enterprise SEO Auditing',
            '_quinrel_schema_service_description' => 'Comprehensive technical crawl, schema and performance audit.',
            '_quinrel_schema_service_type' => 'Search Engine Optimization',
            '_quinrel_schema_service_provider' => 'Digital Agency LLC',
            '_quinrel_schema_service_area' => 'Global',
            '_quinrel_schema_service_price' => '2500',
            '_quinrel_schema_service_currency' => 'EUR',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertSame('Service', $schema['@type']);
        $this->assertSame('Enterprise SEO Auditing', $schema['name']);
        $this->assertSame('Search Engine Optimization', $schema['serviceType']);
        $this->assertSame('Digital Agency LLC', $schema['provider']['name']);
        $this->assertSame('Global', $schema['areaServed']);
        $this->assertSame(2500.0, $schema['offers']['price']);
        $this->assertSame('EUR', $schema['offers']['priceCurrency']);
    }

    public function testReviewSchema(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 607;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Review of Acme Wireless Headphones',
            'post_type' => 'post',
            'post_date' => '2026-10-22 15:00:00',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'review',
            '_quinrel_schema_review_item_name' => 'Acme Pro Sound Headphones',
            '_quinrel_schema_review_item_type' => 'Product',
            '_quinrel_schema_review_rating' => '4.8',
            '_quinrel_schema_review_body' => 'Exceptional soundstage, active noise cancellation, and battery life.',
            '_quinrel_schema_review_author' => 'Tech Audio Reviewer',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertSame('Review', $schema['@type']);
        $this->assertSame('Product', $schema['itemReviewed']['@type']);
        $this->assertSame('Acme Pro Sound Headphones', $schema['itemReviewed']['name']);
        $this->assertSame(4.8, $schema['reviewRating']['ratingValue']);
        $this->assertSame('Tech Audio Reviewer', $schema['author']['name']);
    }

    public function testMovieSchema(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 608;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'The Quantum Horizon',
            'post_type' => 'post',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'movie',
            '_quinrel_schema_movie_name' => 'The Quantum Horizon',
            '_quinrel_schema_movie_director' => 'Christopher Nolan',
            '_quinrel_schema_movie_actors' => 'Cillian Murphy, Emily Blunt, Matt Damon',
            '_quinrel_schema_movie_date' => '2026-07-21',
            '_quinrel_schema_movie_duration' => '2 hours 30 mins',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertSame('Movie', $schema['@type']);
        $this->assertSame('The Quantum Horizon', $schema['name']);
        $this->assertSame('Christopher Nolan', $schema['director']['name']);
        $this->assertCount(3, $schema['actor']);
        $this->assertSame('Cillian Murphy', $schema['actor'][0]['name']);
        $this->assertSame('PT2H30M', $schema['duration']);
    }

    public function testMusicRecordingSchema(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 609;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Midnight Resonance',
            'post_type' => 'post',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'music',
            '_quinrel_schema_music_name' => 'Midnight Resonance',
            '_quinrel_schema_music_artist' => 'Echo Collective',
            '_quinrel_schema_music_album' => 'Synthetic Horizons',
            '_quinrel_schema_music_duration' => '4 min',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertSame('MusicRecording', $schema['@type']);
        $this->assertSame('Midnight Resonance', $schema['name']);
        $this->assertSame('Echo Collective', $schema['byArtist']['name']);
        $this->assertSame('Synthetic Horizons', $schema['inAlbum']['name']);
        $this->assertSame('PT4M', $schema['duration']);
    }

    public function testDatasetSchema(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 610;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Global Carbon Emissions 1990-2025',
            'post_type' => 'post',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'dataset',
            '_quinrel_schema_dataset_name' => 'Global Carbon Emissions 1990-2025',
            '_quinrel_schema_dataset_description' => 'Comprehensive annual dataset of global greenhouse gas emissions.',
            '_quinrel_schema_dataset_license' => 'https://creativecommons.org/licenses/by/4.0/',
            '_quinrel_schema_dataset_download_url' => 'https://example.org/data/emissions.csv',
            '_quinrel_schema_dataset_format' => 'text/csv',
            '_quinrel_schema_dataset_creator' => 'Climate Data Initiative',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertSame('Dataset', $schema['@type']);
        $this->assertSame('Global Carbon Emissions 1990-2025', $schema['name']);
        $this->assertSame('https://creativecommons.org/licenses/by/4.0/', $schema['license']);
        $this->assertSame('https://example.org/data/emissions.csv', $schema['distribution']['contentUrl']);
        $this->assertSame('text/csv', $schema['distribution']['encodingFormat']);
        $this->assertSame('Climate Data Initiative', $schema['creator']['name']);
    }

    public function testPersonSchema(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 611;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Dr. Jane Smith Bio',
            'post_type' => 'post',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'person',
            '_quinrel_schema_person_name' => 'Dr. Jane Smith',
            '_quinrel_schema_person_job_title' => 'Chief Scientist',
            '_quinrel_schema_person_works_for' => 'Quantum Institute',
            '_quinrel_schema_person_same_as' => 'https://twitter.com/janesmith, https://github.com/janesmith',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertSame('Person', $schema['@type']);
        $this->assertSame('Dr. Jane Smith', $schema['name']);
        $this->assertSame('Chief Scientist', $schema['jobTitle']);
        $this->assertSame('Quantum Institute', $schema['worksFor']['name']);
        $this->assertSame(['https://twitter.com/janesmith', 'https://github.com/janesmith'], $schema['sameAs']);
    }

    public function testSchemaExtensibilityFilters(): void
    {
        global $mock_posts;

        $postId = 700;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Extensibility Test',
            'post_type' => 'post',
        ]);

        // Filter quinrel_schema_graph to attach a custom node
        add_filter('quinrel_schema_graph', function (array $graph, int $pid) use ($postId) {
            if ($pid === $postId) {
                $graph['@graph'][] = [
                    '@type' => 'SpecialExtensibilityNode',
                    'name' => 'Custom Third-Party Injected Data',
                ];
            }
            return $graph;
        }, 10, 2);

        $result = $this->schemaGenerator->getSchemaForPost($postId);

        $types = array_column($result['@graph'], '@type');
        $this->assertContains('SpecialExtensibilityNode', $types);
    }
}

