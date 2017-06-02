<?php

defined( 'ABSPATH' ) || exit;

class S3_PostMeta_Patch {

    public function __construct() {
        add_filter( 'as3cf_get_attachment_s3_info', array( $this, 'amazonS3_info_upload' ), 10, 2 );
        add_filter( 'content_edit_pre', array( $this, 'amazonS3_content_edit' ), 10, 2 );
    }

    public function amazonS3_info_upload( $meta, $postId ) {
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

        if (!$this->get_s3_client()->doesObjectExist($s3object['bucket'], $s3object['key'])) {
            return $meta;
        }

        $result = update_post_meta($postId, 'amazonS3_info', $s3object);

        if ($result === false) {
            return $meta;
        }

        return $s3object;
    }

    public function amazonS3_content_edit( $content ) {
        if (empty($content)) {
            return $content;
        }

        /**
         * @var $as3cf \Amazon_S3_And_CloudFront
         */
        global $as3cf;

        $s3Client = $this->get_s3_client();
        $bucket = $as3cf->get_setting('bucket');

        return preg_replace_callback('/(["\'])\/?(data\/uploads[^\\1]*?)\\1/i', function($match) use ($s3Client, $bucket) {
            $path = $match[2];

            if (!$s3Client->doesObjectExist($bucket, $path)) {
                return '/' . $path;
            }

            $replaced = $s3Client->getObjectUrl($bucket, $path);

            return $replaced;
        }, $content);
    }

    public function get_s3_client() {
	    return $this->get_s3_instance()->get_s3client(
	        $this->get_s3_instance()->get_setting('region')
	    );
    }

    public function get_s3_instance() {
        global $as3cf;
	    return $as3cf;
    }

}

$s3_postmeta_patch = new S3_PostMeta_Patch();
