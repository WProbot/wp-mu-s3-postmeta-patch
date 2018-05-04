<?php

defined( 'ABSPATH' ) || exit;

/**
 * Axelspringer_S3PostMetaPatch
 */
class Axelspringer_S3PostMetaPatch {

    public function __construct() {
        add_filter( 'as3cf_get_attachment_s3_info', array( &$this, 'amazonS3_info_upload' ), 10, 2 );
        add_filter( 'content_edit_pre', array( &$this, 'amazonS3_content_edit' ), 10, 2 );

        add_action( 'aws_init', array( &$this, 'aws_init' ), 20 );
    }

    public function aws_init() {
        global $as3cf;

        remove_filter( 'wp_calculate_image_srcset', [ $as3cf->plugin_compat, 'wp_calculate_image_srcset' ] );
        remove_filter( 'wp_get_attachment_url', [ $as3cf, 'wp_get_attachment_url' ], 99 );
        remove_filter( 'the_content', [ $as3cf->filter_local, 'filter_post' ], 100 );
        remove_filter( 'the_excerpt', [ $as3cf->filter_local, 'filter_post' ], 100 );
    }

    public function amazonS3_info_upload( $post_meta, $post_id ) {
        if ( is_array( $post_meta ) ) {
            return $post_meta;
        }

        global $as3cf;

        $file = get_post_meta( $post_id, '_wp_attached_file', true );

        $s3_object = [
            'bucket' => $as3cf->get_setting( 'bucket' ),
            'key'    => $as3cf->get_object_prefix() . $file,
            'region' => $as3cf->get_setting( 'region' ),
            'acl'    => \Amazon_S3_And_CloudFront::DEFAULT_ACL
        ];

        if ( ! $this->get_s3_client()->doesObjectExist( $s3_object['bucket'], $s3_object['key'] ) ) {
            return $post_meta;
        }

        $result = update_post_meta( $post_id, 'amazonS3_info', $s3_object );

        if ( $result === false ) {
            return $post_meta;
        }

        return $s3_object;
    }

    public function amazonS3_content_edit( $content ) {
        if ( empty( $content ) ) {
            return $content;
        }

        global $as3cf;

        $s3Client = $this->get_s3_client();
        $bucket   = $as3cf->get_setting( 'bucket' );

        return preg_replace_callback( '/(["\'])\/?(data\/uploads[^\\1]*?)\\1/i', function ( $match ) use ( $s3Client, $bucket ) {
            $path = $match[2];

            if ( ! $s3Client->doesObjectExist( $bucket, $path ) ) {
                return '/' . $path;
            }

            $replaced = $s3Client->getObjectUrl( $bucket, $path );

            return $replaced;
        }, $content );
    }

    public function get_s3_client() {
        return $this->get_s3_instance()->get_s3client(
            $this->get_s3_instance()->get_setting( 'region' )
        );
    }

    public function get_s3_instance() {
        global $as3cf;

        return $as3cf;
    }

}

$s3_postmeta_patch = new Axelspringer_S3PostMetaPatch();
