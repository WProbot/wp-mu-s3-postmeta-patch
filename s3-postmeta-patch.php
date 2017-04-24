<?php

// @codingStandardsIgnoreFile

/**
 * Images that are not uploaded over the wordpress backend don't have a 'amazonS3_info' entry in the database.
 * We have to make sure, this info exists. Otherwise we are running into unexpected side effects.
 * (e.g. images are not displayed properly in the text-editor)
 */
add_filter('as3cf_get_attachment_s3_info', function($meta, $postId) {
    if (is_array($meta)) {
       return $meta;
    }

    /**
     * @var $as3cf \Amazon_S3_And_CloudFront
     */
    global $as3cf;

    $file = get_post_meta($postId, '_wp_attached_file', true);

    $s3object = [
        'bucket' => $as3cf->get_setting('bucket'),
        'key'    => $as3cf->get_object_prefix() . $file,
        'region' => $as3cf->get_setting('region'),
        'acl'    => \Amazon_S3_And_CloudFront::DEFAULT_ACL
    ];

    if (!get_s3_client()->doesObjectExist($s3object['bucket'], $s3object['key'])) {
        return $meta;
    }

    $result = update_post_meta($postId, 'amazonS3_info', $s3object);

    if ($result === false) {
        return $meta;
    }

    return $s3object;
}, 10, 2);

/**
 * Paths to images are saved with relative paths in the database.
 * For that reason, images are not visible in the text-editor.
 * Therefore we have to prepend the S3 url to every image source that matches a specific pattern.
 */
add_filter('content_edit_pre', function($content) {
    if (empty($content)) {
        return $content;
    }

    /**
     * @var $as3cf \Amazon_S3_And_CloudFront
     */
    global $as3cf;

    $s3Client = get_s3_client();
    $bucket = $as3cf->get_setting('bucket');

    return preg_replace_callback('/(["\'])\/?(data\/uploads[^\\1]*?)\\1/i', function($match) use ($s3Client, $bucket) {
        $path = $match[2];

        if (!$s3Client->doesObjectExist($bucket, $path)) {
            return '/' . $path;
        }

        $replaced = $s3Client->getObjectUrl($bucket, $path);

        return $replaced;
    }, $content);
}, 10, 2);

/**
 * By default, the AmazonS3-Plugin hooks into the process for retrieving urls for images
 * and sets the S3-domain to every image that is marked as "served over S3" in the database (amazonS3_info at wp_postmeta).
 *
 * This behaviour should be disabled for stages there are no "STATIC_URL" is defined.
 */
add_action('aws_init', function() {
    /**
     * @var $as3cf \Amazon_S3_And_CloudFront
     */
    global $as3cf;

    // do not touch any image srcset's urls if no static url is defined
    if (defined('STATIC_URL') && !empty(STATIC_URL)) {
        return;
    }

    remove_filter('wp_calculate_image_srcset', [$as3cf->plugin_compat, 'wp_calculate_image_srcset']);
    remove_filter('wp_get_attachment_url', [$as3cf, 'wp_get_attachment_url'], 99);
}, 20);
