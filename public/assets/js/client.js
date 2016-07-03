var newapp = (function($) {
    'use strict';

    var wpNonce        = '#_wpnonce',
        migrationPanel = '.mwn-migration-tool',
        minorButton    = '.mt-minor-actions',
        majorButton    = '.mt-major-action';

    /**
     * Set panel Class
     *
     * @param classname
     */
    var setPanelClass = function(classname)
    {
        $(migrationPanel).removeClass(function() {
            var regex = new RegExp(migrationPanel.slice(1), 'g');

            return $(this).attr('class').replace(regex, '');
        });

        if (classname.length > 0) {
            $(migrationPanel).addClass(classname);
        }
    };

    /**
     * Set status message
     *
     * @param message
     */
    var setPanelStatus = function(message)
    {
        $(migrationPanel).find('.mt-status-message').html(message);
    };

    /**
     * Post model (to submit data to mwn_migration table)
     */
    var Post = Backbone.Model.extend({
        urlRoot: '/route'
    });

    /**
     * Posts collection (populated with duplicated posts, based on Sajari response)
     */
    var Posts = Backbone.Collection.extend({
        url: '/route',

        parse: function(response) {
            return response.message.body;
        },

        doDuplicationCheck: function(force) {
            var data = {
                action: 'sajari_score',
                nonce: jQuery(wpNonce).val(),
                post_id: jQuery('#post_ID').val()
            };

            if (force) {
                data.force_check = true;
            }

            this.fetch({
                data: data,
                success: function(collection, response, options) {
                    // Check for response, if the post has duplications, display RedirectView
                    // If no duplications -- display migrate view
                    if (response.message.body == 0) {
                        // Migrate View
                        newapp.trigger("renderScreen", new MigrateView());
                    } else {
                        // Redirect View
                        newapp.trigger("renderScreen", new RedirectView({collection: collection}));
                    }
                }
            });
        }
    });

    /**
     * LoadingView View
     */
    var LoadingView = Backbone.View.extend({
        el: '.mt-content',

        initialize: function() {
            this.defaults = {
                class: 'mt-redirect',
                status: 'Please wait a moment.',
                content: '<div class="mt-spinner"><div class="mt-spinner-inner"><span></span></div></div>'
            };
        },

        render: function(viewCustom) {
            var viewOptions = _.defaults(viewCustom, this.defaults);

            // Set loading status
            setPanelStatus(viewOptions.status);

            // Render default loading content
            this.$el.html(viewOptions.content);
        }
    });

    /**
     * RedirectView View
     */
    var RedirectView = Backbone.View.extend({
        el: '.mt-content',

        initialize: function() {
            this.actions = new PanelActions();
            this.defaults = {
                class: 'mt-redirect',
                status: 'This post has been marked for redirection',
                actions: {
                    minor: 'Re-check',
                    major: 'Confirm'
                }
            };
        },

        render: function(viewCustom) {
            var viewOptions = _.defaults(viewCustom, this.defaults);

            // Set class
            setPanelClass(viewOptions.class);

            // Set the status message and class
            setPanelStatus(viewOptions.status);

            // Render content
            var template = _.template(jQuery('#tmpl-duplicate-posts').html(), {posts: this.collection.models});
            this.$el.html(template);

            // Render actions
            this.actions.render(viewOptions.actions);

            // Re-Check: Minor button
            jQuery(minorButton).click(function(e){
                e.preventDefault();

                // Disable button
                $(this).prop('disabled', true);

                // Display loading screen
                newapp.trigger("renderScreen", new LoadingView());

                /**
                 * Re-Check functionality, pass an extra force parameter to enfource
                 * doDuplicationCheck -- Renders Migrate/Redirect screen, depending on Sajari lookup results
                 */
                var posts = new Posts();
                posts.doDuplicationCheck(true);
            });

            // Confirm: Major button (dunno where to place this best)
            jQuery(majorButton).click(function(e){
                e.preventDefault();

                // Disable button
                $(this).prop('disabled', true);

                // Navigate to Confirm redirect screen
                newapp.trigger("renderScreen", new RedirectSuccessView());
            });

            return this;
        }
    });

    /**
     * ConfirmRedirectView View
     */
    var RedirectSuccessView = Backbone.View.extend({
        el: '.mt-content',

        initialize: function() {
            this.actions = new PanelActions();
            this.defaults = {
                class: 'mt-redirect mt-success',
                status: 'Marked for redirection',
                actions: {
                    note: '<span class="mt-count">300</span> posts for Author remaining',
                    minor: 'Undo',
                    major: 'Next post'
                }
            };
        },

        render: function(viewCustom) {
            var viewOptions = _.defaults(viewCustom, this.defaults);

            // Set class
            setPanelClass(viewOptions.class);

            // Set the status message
            setPanelStatus(viewOptions.status);

            // Render content
            this.$el.html('<p class="mt-panel-info"><span class="dashicons"></span><strong>Success!</strong> This post has be marked for redirection.</p>');

            // Render actions
            this.actions.render(viewOptions.actions);

            // Undo button
            jQuery(minorButton).click(function(e){
                e.preventDefault();

                // Disable button
                $(this).prop('disabled', true);

                // save model (do undo request)

                // do next post button
                alert('Undo action');
            });

            // Next post
            jQuery(majorButton).click(function(e){
                e.preventDefault();

                // do next post button
                console.log('Next post');

                alert('Next post');
            });

            return this;
        }
    });

    /**
     * MigrateView View
     */
    var MigrateView = Backbone.View.extend({
        el: '.mt-content',

        initialize: function() {
            this.actions = new PanelActions();
            this.defaults = {
                class: 'mt-migrate',
                status: 'No significant duplication of this post\'s content found',
                content: '<p class="mt-panel-info"><span class="dashicons"></span>This post has been pre-approved for migration. Click <strong>confirm</strong> to send this post to the migration queue.</p>',
                actions: {
                    major: 'Confirm'
                }
            };
        },

        render: function(viewCustom) {
            var viewOptions = _.defaults(viewCustom, this.defaults);

            // Set class
            setPanelClass(viewOptions.class);

            // Set loading status
            setPanelStatus(viewOptions.status);

            // Render default loading content
            this.$el.html(viewOptions.content);

            // Render actions
            this.actions.render(viewOptions.actions);

            // Next post
            jQuery(majorButton).click(function(e){
                e.preventDefault();

                // do next post button
                console.log('Confirm');

                alert('Confirm');
            });
        }
    });

    /**
     * Migrate success View
     */
    var MigrateSuccessView = Backbone.View.extend({
        el: '.mt-content',

        initialize: function() {
            this.actions = new PanelActions();
            this.defaults = {
                class: 'mt-migrate mt-success',
                status: 'Marked for migration',
                content: '<p class="mt-panel-info"><span class="dashicons"></span><strong>Success!</strong> This post has be marked for migration.</p>',
                actions: {
                    note: '<span class="mt-count">300</span> posts for Author remaining',
                    minor: 'Undo',
                    major: 'Confirm'
                }
            };
        },

        render: function(viewCustom) {
            var viewOptions = _.defaults(viewCustom, this.defaults);

            // Set class
            setPanelClass(viewOptions.class);

            // Set loading status
            setPanelStatus(viewOptions.status);

            // Render default loading content
            this.$el.html(viewOptions.content);

            // Render actions
            this.actions.render(viewOptions.actions);
        }
    });

    /**
     * Partial: PanelActions View
     */
    var PanelActions = Backbone.View.extend({
        el: '.mt-actions',

        render: function(actions) {
            var template = _.template(jQuery('#tmpl-actions').html(), {actions: actions});
            this.$el.html(template);
            return this;
        }
    });

    /**
     * Backbone Router
     */
    var Router = Backbone.Router.extend({
        routes: {
            '': 'home'
            //'redirect': 'redirect',
            //'redirect-confirm': 'redirect-confirm'
        }
    });

    return {
        start: function() {
            var router = new Router();

            /** Adds custom Render event */
            _.extend(newapp, Backbone.Events);

            newapp.on("renderScreen", function(viewObj, options) {
                options = options || {};
                viewObj.render(options);
            });

            /** Router */
            router.on('route:home', function() {
                // Trigger loading screen
                newapp.trigger("renderScreen", new LoadingView());

                /**
                 * On every page load fire AJAX to check for duplications and populate the posts collection
                 * doDuplicationCheck -- Renders Migrate/Redirect screen, depending on Sajari lookup results
                 */
                var posts = new Posts();
                posts.doDuplicationCheck();
            });

            // Start the home router
            Backbone.history.start();

            /**
            newapp.router.on('route:redirect', function() {
                newapp.redirectView.render({
                    class: 'mt-redirect',
                    status: 'These articles were found to have a significant duplication of the content in this post.atus message',
                    actions: {
                        minor: 'Re-check',
                        major: 'Confirm'
                    }
                });
            });

            newapp.router.on('route:redirect-confirm', function() {
                newapp.confirmRedirect.render({
                    class: 'mt-redirect mt-success',
                    status: 'Confirm redirect'
                });
            });
             */
        }
    }
}(jQuery));