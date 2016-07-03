<?php

namespace Mwn\Migration;

use Mwn\Migration\Api\Sajari as Sajari;

class Migration
{
    const ESCAPED_CHARACTERS = ',;|';

    public $postMetaName = '_sajari_score';
    public $rawscore = 0.3;

    /** @var Sajari $sajariApi */
    public $sajariApi;

    /**
     * Migration constructor.
     */
    public function __construct()
    {
        $this->sajariApi = new Sajari();

        // Add scripts
        add_action("admin_enqueue_scripts", array($this, 'adminEnqueueScripts'));

        // Ajax action
        add_action('wp_ajax_sajari_score', array($this, 'ajaxSajariScore'));

        // Insert Migration Panel
        add_action('edit_form_top', array($this, 'migrationPanelHtml'));

        // Get the preparation where functionality for populating mwn_migrate and post_meta tables is there
        $preparation = new Preparation();
        add_action('wp_ajax_prepare_migration', array($preparation, 'prepareToMigrateListener'));
        add_action('wp_ajax_migration_status', array($preparation, 'getMigrationStatus'));
    }

    /**
     * Print migration panel
     *
     * @param $post
     * @return bool
     */
    public function migrationPanelHtml($post)
    {
        global $pagenow;

        if (get_current_screen()->parent_base != 'edit' || get_current_screen()->id != 'post' || $pagenow != 'post.php') {
            return false;
        }

        // Extra check for post status and current user capacity
        if ('publish' == $post->post_status && 'post' == $post->post_type && current_user_can('edit_posts')) {
            // Display default panel
            //include __DIR__ . DIRECTORY_SEPARATOR . 'template.php';

            // TODO: add real status check, i.e: redirect, migrate, duplication check etc.
            $status = 0;

            // Init MWN Migration JS to process with Ajax callback
            ?>
            <div class="mwn-migration-tool">
                <h2 class="mt-title">
                    <span class="dashicons"></span>
                    <span class="mt-status-title"></span>
                </h2>
                <p class="mt-status-message">
                    <!-- status message goes here -->
                </p>

                <div class="mt-content">
                    <!-- content goes here -->
                </div>

                <div class="mt-actions">
                    <!-- actions elements go here -->
                </div>

                <div class="mt-help">
                    <p>If something goes wrong <a href="#" class="button">Send error report</a></p>
                </div>
            </div>

            <!-- Actions template -->
            <script id="tmpl-actions" type="text/html">
                <% _.each( actions, function( action, key ) { %>
                    <% if ('note' === key) { %>
                        <div class="mt-action-note"><p><%= action %></p></div>
                    <% } %>

                    <% if ('minor' === key) { %>
                        <div class="mt-minor-actions"><button class="button button-hero"><%= action %></button></div>
                    <% } %>

                    <% if ('major' === key) { %>
                        <div class="mt-major-action"><button class="button button-primary button-hero"><%= action %></button></div>
                    <% } %>
                <% }); %>
                <div class="clear"></div>
            </script>

            <!-- Post duplications template -->
            <script id="tmpl-duplicate-posts" type="text/html">
                <p class="mt-panel-info"><span class="dashicons"></span>Please <strong>select a redirect destination</strong> or modify this post and re-check.</p>
                <fieldset>
                <ul class="mt-radio-option-list">
                    <% _.each(posts, function(post, index) { %>
                        <li class="mt-article">
                            <div class="mt-radio-button"><input type="radio" name="mt-redirect-option" id="mt-redirect-url-<%= index %>" value="url"></div>
                            <label class="mt-radio-label" for="mt-redirect-url-<%= index %>">
                                <span class="dashicons"></span>
                                <span class="mt-similar-score"><i style="width: <% print(post.get('rawscore') * 100); %>%;"></i></span>
                                <span class="mt-duplicate-title"><%= post.get('title') %></span>
                                <span class="mt-duplicate-link"><a href="http://www.mamamia.com.au/<%= post.get('path') %>" target="_blank">mamamia.com.au/<%= post.get('path') %></a></span>
                                <span class="mt-view"><a class="button" href="http://www.mamamia.com.au/<%= post.get('path') %>" target="_blank">View post</a></span>
                            </label><div class="clear"></div>
                        </li>
                    <% }) %>

                    <!-- Category -->
                    <li class="mt-category">
                        <div class="mt-radio-button"><input type="radio" name="mt-redirect-option" id="mt-redirect-cat" value="url"></div>
                        <label class="mt-radio-label" for="mt-redirect-cat">
                            <span class="dashicons"></span>
                            <span class="mt-duplicate-title">Category Index</span>
                            <span class="mt-duplicate-link">mamamia.com.au/
                                <select name="mt-redirect-cat-value" id="mt-redirect-cat-value"><option>News</option><option>Entertainment</option><option>Lifestyle</option> <option>Parenting</option><option>Beauty &amp; Style</option><option>Health</option><option>Relationships</option><option>Video</option></select>
                            </span></label><div class="clear"></div>
                    </li>

                    <!-- Home option -->
                    <li class="mt-home">
                        <div class="mt-radio-button"><input type="radio" name="mt-redirect-option" id="mt-redirect-home" value="url"></div>
                        <label class="mt-radio-label" for="mt-redirect-home">
                            <span class="dashicons"></span>
                            <span class="mt-duplicate-title">Mamamia Home</span>
                            <span class="mt-duplicate-link">mamamia.com.au</span>
                        </label><div class="clear"></div>
                    </li>
                </ul>
                </fieldset>
            </script>

            <?php
                echo '<script type="application/javascript">
                    jQuery(document).ready(function() {
                        newapp.start();
                        //MWNMigration.init(' . $status . ');
                    });
                </script>';

        }
    }

    public function ajaxSajariScore()
    {
        // Get the post object
        $postId = intval($_REQUEST['post_id']);
        $post   = get_post($postId);

        /**
         * If ReCheck button clicked -- remove postmeta and get Sajari data again.
         */
        if (isset($_REQUEST['force_check']) && true == $_REQUEST['force_check']) {
            // Reset existing post_meta
            $this->setPostStatus($post->ID, null);
            $sajariResponse = '';
        } else {
            // Read post_meta to get Sajari data
            $sajariResponse = $this->getPostStatus($post->ID);
        }

        // If no data stored, process with new Sajari duplication check
        if (empty($sajariResponse) || is_null($sajariResponse)) {
            // Get the current post URL
            $postUrl = str_replace(array('stage.', 'dev.', 'new.'), 'www.', get_permalink($post->ID));

            // First, do PATH duplication check
            //$duplicatePath = $this->doPathLookup($postData['path']);

            // Second, do TITLE duplication check
            //$duplicateTitle = $this->doTitleLookup($this->escape($postData['title']));

            // Third, do BODY duplication check (possibly long-runner)
            $duplicateBody = $this->doBodyLookup($postUrl);

            $postMetaScore = [
                'body'  => (!empty($duplicateBody)) ? $duplicateBody : 0
            ];

            // Save values in post meta
            $this->setPostStatus($post->ID, $postMetaScore);

            // Render
            $this->noticeSuccess($postMetaScore);

        } else {
            $this->noticeSuccess($sajariResponse);
        }
    }

    /**
     * Add admin scripts
     * @param $hook
     */
    public function adminEnqueueScripts($hook)
    {
        if ('post.php' == $hook) {
            wp_enqueue_script(
                'migration-scripts',
                home_url() . '/app/mwn-codepool/src/Mwn/Migration/assets/migration-admin.js',
                ['backbone']
            );

            wp_enqueue_style(
                'mt_admin_css',
                home_url() . '/app/mwn-codepool/src/Mwn/Migration/assets/migration.css'
            );
        }
    }

    /**
     * @param $path
     * @return array
     */
    protected function doPathLookup($path)
    {
        $duplicate = [];

        $check = $this->doLookup(array(
            'filters' => '~path,' . $path,
            'maxresults' => '3'
        ));

        if (isset($check) && !empty($check->results)) {
            /**
             * Save all posts with rawscore > 0.4 (probably duplicate)
             */
            foreach ($check->results as $result) {
                if (intval($result->rawscore) > $this->rawscore) {
                    $duplicate[] = [
                        'rawscore' => $result->rawscore,
                        'title'    => $result->meta->title,
                        'path'     => $result->meta->path
                    ];
                }
            }
        }

        return $duplicate;
    }

    /**
     * @param $title
     * @return array
     */
    protected function doTitleLookup($title)
    {
        $duplicate = [];

        $check = $this->doLookup(array(
            'filters' => '~title,' . $title,
            'maxresults' => '3'
        ));

        if (isset($check) && !empty($check->results)) {
            /**
             * Save all posts with rawscore > 0.4 (probably duplicate)
             */
            foreach ($check->results as $result) {
                if (intval($result->rawscore) > $this->rawscore) {
                    $duplicate[] = [
                        'rawscore' => $result->rawscore,
                        'title'    => $result->meta->title,
                        'path'     => $result->meta->path
                    ];
                }
            }
        }

        return $duplicate;
    }

    /**
     * @param $url
     * @return array
     */
    protected function doBodyLookup($url)
    {
        $duplicate = [];

        $check = $this->doLookup(array(
            'q'          => $url,
            'mimetype'   => 'text/url',
            'maxresults' => '3'
        ));

        if (isset($check) && !empty($check->results)) {
            // Sort an array of objects by rawscore
            usort($check->results, function ($a, $b) {
                return strcmp($b->rawscore, $a->rawscore);
            });

            /**
             * Save all posts with rawscore > 0.3 (probably duplicate)
             */
            foreach ($check->results as $result) {
                if ((float) $result->rawscore > $this->rawscore) {
                    $duplicate[] = [
                        'rawscore' => $result->rawscore,
                        'title'    => $result->meta->title,
                        'path'     => $result->meta->path
                    ];
                }
            }
        }

        return $duplicate;
    }

    /**
     * @param array $data
     * @return null
     */
    protected function doLookup($data)
    {
        $pathCheck = null;

        try {
            // Call search endpoint
            $pathCheck = $this->sajariApi->search($data);

        } catch (\Exception $e) {
            $message = $e->getMessage();

            // Json error message
            $this->noticeError($message);
        }

        return $pathCheck;
    }

    private function escape($string)
    {
        $escaped_chars = str_split(self::ESCAPED_CHARACTERS);
        $escaped_chars_replace = array_map(function ($char) {
            return '\\' . $char;
        }, $escaped_chars);
        $string = str_replace($escaped_chars, $escaped_chars_replace, $string);

        // Ignored whitespace.
        return preg_replace('/\s+/u', ' ', $string);
    }

    /**
     * @param $postId
     * @return null|string
     */
    private function getPostStatus($postId)
    {
        //global $wpdb;
        //$query = "SELECT migrate FROM mwn_migrate WHERE post_id=%d";
        //$status = $wpdb->get_var($wpdb->prepare($query, $postId));
        //return $status;

        // Based on post_meta for now
        $sajariResponse = get_post_meta($postId, $this->postMetaName, true);

        return $sajariResponse;
    }

    /**
     * Set the AppleNews metadata
     *
     * @param $postId
     * @param $data
     */
    private function setPostStatus($postId, $data = null)
    {
        if (empty($data)) {
            delete_post_meta($postId, $this->postMetaName);
        } else {
            update_post_meta($postId, $this->postMetaName, $data);
        }
    }

    /**
     * Displays a success message.
     *
     * @param $message
     */
    private function noticeSuccess($message)
    {
        header('Content-Type: application/json');
        echo json_encode(array('status' => 'success', 'message'=> $message));
        exit;
    }

    /**
     * Displays an error message.
     *
     * @param $message
     */
    private function noticeError($message)
    {
        header('Content-Type: application/json');
        echo json_encode(array('status' => 'error', 'message'=> $message));
        exit;
    }
}
