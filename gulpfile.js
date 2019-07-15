/**
 * Boilerplate
 *
 * @todo: Run the starterkit from repmaster or define a custom theme task and
 *   add to the default build task below. The repmaster starterkit script
 *   provides a theme task encapsulating its three default steps, compiling
 *   sass, copying bootstrap's js libraries, and assembling assets. A variable
 *   will be added matching the name of the new theme, which can be used to add
 *   its exported theme property to the build task.
 *   e.g.:
 *     var myNewTheme = require('./web/themes/custom/myNewTheme/gulpfile.js');
 *     var build = gulp.parallel(libraries, myNewTheme.theme);
 *
 * @todo: Remove this comment block after customizing.
 */
var gulp = require('gulp');

function libraries__ckeditor_codemirror_plugin() {
  return gulp.src(['node_modules/ckeditor-codemirror-plugin/**'], { base: 'node_modules/ckeditor-codemirror-plugin' })
    .pipe(gulp.dest('web/libraries/ckeditor_codemirror/'));
}
var libraries = gulp.parallel(libraries__ckeditor_codemirror_plugin);
exports.libraries = libraries;

/* *** BP:THEME *** */

var build = gulp.parallel(libraries);

exports.build = build;
exports.default = build;
