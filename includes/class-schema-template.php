<?php
/**
 * Master schema template builder.
 * Builds static/deterministic parts of the JSON-LD graph.
 */
defined('ABSPATH') || exit;

class Schema_Genie_AI_Template {

    const SITE_URL = 'https://ra-cocron.de';

    public function get_master_template(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) return [];

        $permalink = get_permalink($post_id);
        $title     = get_the_title($post_id);

        $thumbnail_id  = get_post_thumbnail_id($post_id);
        $thumbnail_url = '';
        $thumbnail_w   = 0;
        $thumbnail_h   = 0;
        if ($thumbnail_id) {
            $img = wp_get_attachment_image_src($thumbnail_id, 'full');
            if ($img) {
                $thumbnail_url = $img[0];
                $thumbnail_w   = $img[1];
                $thumbnail_h   = $img[2];
            }
        }

        $entities = [];

        // 1. Place
        $entities[] = [
            '@type' => 'Place',
            '@id'   => self::SITE_URL . '/#place',
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => 'Dircksenstraße 52',
                'addressLocality' => 'Berlin-Mitte',
                'addressRegion'   => 'Berlin',
                'postalCode'      => '10178',
                'addressCountry'  => 'DE',
            ],
        ];

        // 2. Organization + LegalService (combined type)
        $entities[] = [
            '@type' => ['LegalService', 'Organization'],
            '@id'   => self::SITE_URL . '/#organization',
            'name'  => 'Rechtsanwalt Cocron GmbH & Co. KG',
            'url'   => self::SITE_URL,
            'email' => 'mailto:kontakt@ra-cocron.de',
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => 'Dircksenstraße 52',
                'addressLocality' => 'Berlin-Mitte',
                'addressRegion'   => 'Berlin',
                'postalCode'      => '10178',
                'addressCountry'  => 'DE',
            ],
            'logo' => [
                '@type'      => 'ImageObject',
                '@id'        => self::SITE_URL . '/#logo',
                'url'        => self::SITE_URL . '/wp-content/uploads/2024/04/cr-logo-img.webp',
                'contentUrl' => self::SITE_URL . '/wp-content/uploads/2024/04/cr-logo-img.webp',
                'caption'    => 'Rechtsanwalt Cocron GmbH & Co. KG',
                'inLanguage' => 'en-US',
                'width'      => 420,
                'height'     => 88,
            ],
            'openingHours' => [
                'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday 09:00-17:00',
            ],
            'vatID'     => 'DE450779488',
            'location'  => ['@id' => self::SITE_URL . '/#place'],
            'image'     => ['@id' => self::SITE_URL . '/#logo'],
            'telephone' => '+49-30-814-5909-00',
        ];

        // 3. WebSite
        $entities[] = [
            '@type'         => 'WebSite',
            '@id'           => self::SITE_URL . '/#website',
            'url'           => self::SITE_URL,
            'name'          => 'Rechtsanwalt Cocron GmbH & Co. KG',
            'alternateName' => 'Rechtsanwalt Cocron',
            'publisher'     => ['@id' => self::SITE_URL . '/#organization'],
            'inLanguage'    => 'en-US',
        ];

        // 4. Primary Image
        $og_image_url = self::SITE_URL . '/wp-content/uploads/2025/01/og-img.png';
        if (!empty($thumbnail_url)) {
            $entities[] = [
                '@type'      => 'ImageObject',
                '@id'        => $thumbnail_url,
                'url'        => $thumbnail_url,
                'width'      => $thumbnail_w,
                'height'     => $thumbnail_h,
                'inLanguage' => 'en-US',
            ];
        } else {
            $entities[] = [
                '@type'      => 'ImageObject',
                '@id'        => $og_image_url,
                'url'        => $og_image_url,
                'width'      => 1200,
                'height'     => 630,
                'inLanguage' => 'en-US',
            ];
        }

        // 5. WebPage
        $entities[] = [
            '@type'              => 'WebPage',
            '@id'                => $permalink . '#webpage',
            'url'                => $permalink,
            'name'               => $title . ' - Cocron Rechtsanwalt',
            'datePublished'      => get_the_date('c', $post_id),
            'dateModified'       => get_the_modified_date('c', $post_id),
            'isPartOf'           => ['@id' => self::SITE_URL . '/#website'],
            'primaryImageOfPage' => [
                '@id' => !empty($thumbnail_url) ? $thumbnail_url : $og_image_url,
            ],
            'inLanguage' => 'en-US',
        ];

        // 6. Person (author)
        $entities[] = [
            '@type'    => 'Person',
            'name'     => 'István Cocron',
            'jobTitle' => 'Rechtsanwalt',
            'worksFor' => [
                '@type' => 'Organization',
                'name'  => 'Rechtsanwalt Cocron GmbH & Co. KG',
                'url'   => self::SITE_URL,
            ],
        ];

        // 7. Organization (standalone, with sameAs)
        $entities[] = [
            '@type'   => 'Organization',
            'name'    => 'Rechtsanwalt Cocron GmbH & Co. KG',
            'url'     => self::SITE_URL,
            'logo'    => self::SITE_URL . '/wp-content/uploads/2024/06/cr-favicon.png',
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => 'Dircksenstraße 52',
                'addressLocality' => 'Berlin',
                'postalCode'      => '10178',
                'addressCountry'  => 'DE',
            ],
            'sameAs' => [
                'https://www.linkedin.com/company/cocron-rechtsanwaelte/',
                'https://www.xing.com/pages/cocronrechtsanwaelte',
            ],
        ];

        return $entities;
    }

    public function get_service_cities(): array {
        return ['Berlin', 'München'];
    }
}
