"use strict";

var gulp = require("gulp");
var sass = require("gulp-sass");
var rename = require("gulp-rename");
var plumber = require("gulp-plumber");
var postcss = require("gulp-postcss");
var autoprefixer = require("autoprefixer");
var mqpacker = require("css-mqpacker");
var csso = require("gulp-csso");
var sequence = require("gulp-sequence");
var del = require("del");
var shell = require('gulp-shell');
var jsmin = require('gulp-jsmin');


gulp.task("clean", function() {
  del("styles.css");
});

gulp.task("style", function() {
  gulp.src("scss/style.scss")
    .pipe(plumber())
    .pipe(sass())
    .pipe(postcss([
      autoprefixer({browsers: ["last 2 versions"]}),
      mqpacker({sort: true})
    ]))
    .pipe(rename("styles.css"))
    .pipe(gulp.dest('.'));
    // .pipe(csso())
    // .pipe(rename("style.css"))
    // .pipe(gulp.dest("../blocks/search_custom/css"))
    // .pipe(server.stream());
});


gulp.task('purge_caches', shell.task('cd /var/www/davidson/admin/cli && php purge_caches.php'))

// minify js
gulp.task('clean_js', function() {
  return del('amd/build/*.js');
});

gulp.task('min', function() {
    gulp.src('amd/src/*.js')
        .pipe(jsmin())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('amd/build'));
});

gulp.task('minjs', function(cb) {
  sequence('clean_js', 'min', cb);
});

gulp.watch("scss/**/*.{scss,sass}", ["style", 'purge_caches']);
gulp.watch("amd/src/*.js", ["minjs", 'purge_caches']);

gulp.task("build", function(cb) {
  sequence (
    'clean',
    'style',
    'minjs',
    'purge_caches',
    cb
  );
});
