module.exports = function(grunt) {
    'use strict';

    // Project configuration
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        // Check text domain
        checktextdomain: {
            options: {
                text_domain: 'buddypress-favorite-notification',
                keywords: [
                    '__:1,2d',
                    '_e:1,2d',
                    '_x:1,2c,3d',
                    'esc_html__:1,2d',
                    'esc_html_e:1,2d',
                    'esc_html_x:1,2c,3d',
                    'esc_attr__:1,2d',
                    'esc_attr_e:1,2d',
                    'esc_attr_x:1,2c,3d',
                    '_ex:1,2c,3d',
                    '_n:1,2,4d',
                    '_nx:1,2,4c,5d',
                    '_n_noop:1,2,3d',
                    '_nx_noop:1,2,3c,4d'
                ]
            },
            files: {
                src: [
                    '**/*.php',
                    '!node_modules/**',
                    '!vendor/**',
                    '!dist/**',
                    '!tests/**'
                ],
                expand: true
            }
        },

        // Generate POT file
        makepot: {
            target: {
                options: {
                    domainPath: '/languages',
                    potFilename: 'buddypress-favorite-notification.pot',
                    mainFile: 'bp-favorite-notification.php',
                    type: 'wp-plugin',
                    exclude: [
                        'node_modules/.*',
                        'vendor/.*',
                        'dist/.*',
                        'tests/.*'
                    ],
                    potHeaders: {
                        'poedit': true,
                        'x-poedit-keywordslist': true,
                        'report-msgid-bugs-to': 'https://wbcomdesigns.com/support/',
                        'last-translator': 'Wbcom Designs <admin@wbcomdesigns.com>',
                        'language-team': 'Wbcom Designs <admin@wbcomdesigns.com>'
                    },
                    updateTimestamp: true,
                    processPot: function(pot) {
                        // Add plugin header comment
                        pot.headers['project-id-version'] = 'BuddyPress Favorite Notification 2.0.1';
                        return pot;
                    }
                }
            }
        },

        // Watch for changes
        watch: {
            php: {
                files: ['**/*.php'],
                tasks: ['checktextdomain']
            },
            pot: {
                files: ['**/*.php'],
                tasks: ['makepot']
            }
        }
    });

    // Load npm tasks
    grunt.loadNpmTasks('grunt-checktextdomain');
    grunt.loadNpmTasks('grunt-wp-i18n');

    // Register tasks
    grunt.registerTask('default', ['checktextdomain', 'makepot']);

    grunt.registerTask('check', [
        'checktextdomain'
    ]);

    grunt.registerTask('i18n', [
        'checktextdomain',
        'makepot'
    ]);

    // Release zip: bin/build-release.sh (WordPress.org folder standard).
};
