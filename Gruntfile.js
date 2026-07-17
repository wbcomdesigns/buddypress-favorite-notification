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

        // Clean dist directory
        clean: {
            dist: ['dist'],
            build: ['dist/<%= pkg.name %>']
        },

        // Copy files to dist
        copy: {
            build: {
                src: [
                    '**',
                    // Dev-only tooling (PHPStan + stubs). Never ship these.
                    '!vendor/**',
                    '!composer.json',
                    '!composer.lock',
                    '!phpstan.neon',
                    '!phpstan-baseline.neon',
                    '!node_modules/**',
                    '!audit/**',
                    '!docs/**',
                    '!marketing/**',
                    '!**/*.md',
                    '!*.md',
                    '!.wbcom-i18n.json',
                    '!vendor/**',
                    '!dist/**',
                    '!tests/**',
                    '!bin/**',
                    '!.git/**',
                    '!.github/**',
                    '!.gitignore',
                    '!.gitattributes',
                    '!Gruntfile.js',
                    '!package.json',
                    '!package-lock.json',
                    '!composer.json',
                    '!composer.lock',
                    '!phpcs.xml',
                    '!phpunit.xml',
                    '!README.md',
                    '!CHANGELOG.md',
                    '!TESTING-CHECKLIST.md',
                    '!FAVORITE-COUNT-FEATURE-PLAN.md',
                    '!FAVORITE-COUNT-IMPLEMENTATION.md',
                    '!BUILD.md',
                    '!*.log'
                ],
                dest: 'dist/<%= pkg.name %>/'
            }
        },

        // Create ZIP file for WordPress.org
        compress: {
            build: {
                options: {
                    archive: 'dist/<%= pkg.name %>-<%= pkg.version %>.zip',
                    mode: 'zip'
                },
                files: [
                    {
                        expand: true,
                        cwd: 'dist/<%= pkg.name %>/',
                        src: ['**/*'],
                        dest: '<%= pkg.name %>/'
                    }
                ]
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
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-compress');

    // Register tasks
    grunt.registerTask('default', ['checktextdomain', 'makepot']);

    grunt.registerTask('check', [
        'checktextdomain'
    ]);

    grunt.registerTask('i18n', [
        'checktextdomain',
        'makepot'
    ]);

    grunt.registerTask('build', [
        'checktextdomain',
        'makepot',
        'clean:dist',
        'copy:build',
        'compress:build',
        'clean:build'
    ]);

    // Custom task to display build info
    grunt.registerTask('build-info', 'Display build information', function() {
        grunt.log.writeln('');
        grunt.log.writeln('========================================');
        grunt.log.writeln('Build Complete!');
        grunt.log.writeln('========================================');
        grunt.log.writeln('Plugin: ' + grunt.config('pkg.name'));
        grunt.log.writeln('Version: ' + grunt.config('pkg.version'));
        grunt.log.writeln('Package: dist/' + grunt.config('pkg.name') + '-' + grunt.config('pkg.version') + '.zip');
        grunt.log.writeln('========================================');
        grunt.log.writeln('');
    });

    // Add build info to build task
    grunt.task.run('build-info');
};
