// Gulp.js configuration
'use strict';

const

    domainName = "[TO_BE_COMPLETED]",
    type = "plugins",
    projectName = "inv_import",
    serverPath = '[TO_BE_COMPLETED]',


    // source and build folders
    dir = {
        src: 'src/',
        build: serverPath + domainName + '/wp-content/' + type + '/' + projectName + '/',
        dist: 'dist/' + projectName + '/',
        distCustom: 'dist/'
    },

    // Gulp and plugins
    gulp = require('gulp'),
    gutil = require('gulp-util'),
    newer = require('gulp-newer'),
    stripdebug = require('gulp-strip-debug'),
    debug = require('gulp-debug'),
    gulpdel = require('del'),
    composer = require('gulp-composer'),
    webpackstream = require('webpack-stream'),
    sass = require('gulp-sass')(require('sass')),
    postcss = require('gulp-postcss')
    ;



// Browser-sync
var browsersync = false;
var destFolder = dir.build;

let /** @type {import("gulp-zip")} */ gulpzip;

function setDevEnv(cb) {
    destFolder = dir.build;
    setEnv(destFolder);
    cb();
}

function setProdEnv(cb) {
    destFolder = dir.dist;
    setEnv(destFolder);
    cb();
}

function setEnv(destFold) {
    return;
}

function clean() {
    return gulpdel(destFolder, { force: true });
};

const startup = async () => {
    // @ts-ignore
    gulpzip = (await import("gulp-zip")).default;
};

// run this task before any that require imagemin
async function startupWrapper() {
    await startup();
};

//vendors settings
var vendors = {
    srcNode: 'node_modules/',
    src: dir.src + 'vendor/',
    build: destFolder + 'vendor',
};

// PHP settings
var php = {
    src: '**/*.php',
    build: destFolder
};

// copy PHP files
function phpCopy() {
    php.build = destFolder;
    return gulp.src([php.src, "!dist/**"])
        .pipe(debug({ title: "Php_task:" }))
        .pipe(newer(php.build))
        .pipe(gulp.dest(php.build))
        .pipe(browsersync ? browsersync.reload({ stream: true }) : gutil.noop());
};

var geojson = {
    src: 'geojson/**/*.geojson',
    build: destFolder + '/geojson'
};

// copy PHP files
function geojsonCopy() {
    geojson.build = destFolder + '/geojson';
    return gulp.src([geojson.src, "!dist/**"])
        .pipe(debug({ title: "geojson_task:" }))
        .pipe(newer(geojson.build))
        .pipe(gulp.dest(geojson.build))
        .pipe(browsersync ? browsersync.reload({ stream: true }) : gutil.noop());
};


function composer_copy() {
    return gulp.src('composer.json')
        .pipe(debug({ title: "composer task:" }))
        .pipe(newer(destFolder))
        .pipe(gulp.dest(destFolder))
        .pipe(browsersync ? browsersync.reload({ stream: true }) : gutil.noop());
}

function vendors_lib() {
    vendors.build = destFolder + '/vendor';
    return composer({
        "working-dir": destFolder,
        "async": false
    })
        .pipe(browsersync ? browsersync.reload({ stream: true }) : gutil.noop());
};



function webpack_bundle() {
    return gulp.src('src/index.js')
        .pipe(debug({ title: "webpack_task:" }))
        .pipe(webpackstream(require('./webpack.config.js')))
        .pipe(gulp.dest(destFolder + '/js'));
};

//CSS settings
var css = {
    src: 'scss/style.scss',
    srcAdmin: 'scss/admin.scss',
    watch: destFolder + 'scss/**/*.scss',
    build: destFolder + "css",

    sassOpts: {
        outputStyle: 'expanded',
        precision: 3,
        errLogToConsole: true
    },

    processors: [
        require('postcss-assets')({
            loadPaths: ['images/'],
            basePath: destFolder,
            baseUrl: '/wp-content/' + type + '/' + projectName + '/'
        }),
        require('postcss-sort-media-queries')(),
        require('autoprefixer')(),
        require('cssnano')
    ]
};


//CSS processing
function cssTaskAdmin() {
    css.build = destFolder + '/css';
    return gulp.src(css.srcAdmin)
        .pipe(debug({ title: "CSS_task admin:" }))
        .pipe(sass(css.sassOpts))
        .pipe(postcss(css.processors))
        .pipe(gulp.dest(css.build))
        .pipe(browsersync ? browsersync.reload({ stream: true }) : gutil.noop());
};

//Browsersync options
var syncOpts = {
    proxy: domainName,
    files: dir.build + '**/*',
    open: false,
    notify: false,
    ghostMode: false,
    ui: {
        port: 8001
    }
};

// text domain files
var languages={
    src   : 'languages/**/*.mo',
    dist  : destFolder + 'languages/'
  }
  
function languagesCopy () {
languages.dist = destFolder + 'languages/';
return gulp.src(languages.src)
  .pipe(debug({title: "languages_task:"}))
  .pipe(newer(languages.dist))
  .pipe(gulp.dest(languages.dist))
  .pipe(browsersync ? browsersync.reload({ stream: true }) : gutil.noop()); 
}


// browser-sync
function browsersyncManagement(cb) {
    if (browsersync === false) {
        browsersync = require('browser-sync').create();
        browsersync.init(syncOpts);
    }
    cb();
};

//watch for file changes
function watch(cb) {

    // page changes
    gulp.watch(dir.src, webpack_bundle)
    gulp.watch(php.src, gulp.series(browsersyncManagement, phpCopy));
    gulp.watch(languages.src, gulp.series(browsersyncManagement, languagesCopy));
    gulp.watch(geojson.src, gulp.series(browsersyncManagement, geojsonCopy));
    gulp.watch(css.srcAdmin, gulp.series(browsersyncManagement, cssTaskAdmin));

    gulp.watch(dir.src + '**/composer.json', gulp.series(browsersyncManagement, composer_copy, vendors_lib));

    cb();

};

function zipAll() {
    return gulp.src(dir.dist+"/*")
        .pipe(gulpzip(projectName + '.zip'))
        .pipe(gulp.dest('dist'))
}

exports.cleanDev = gulp.series(
    startupWrapper,
    setDevEnv,
    clean
);

//run distrib tasks
exports.dist = gulp.series(    
    startupWrapper,
    setProdEnv,
    clean,
    gulp.parallel(gulp.series(phpCopy, geojsonCopy,languagesCopy)),
    gulp.parallel(gulp.series(composer_copy, vendors_lib)),
    gulp.parallel(gulp.series(cssTaskAdmin, webpack_bundle)),
    zipAll
);

//default task
exports.default = gulp.series(
    startupWrapper,
    setDevEnv,
    gulp.parallel(gulp.series(phpCopy, geojsonCopy,languagesCopy)),
    gulp.parallel(gulp.series(composer_copy, vendors_lib)),
    gulp.parallel(gulp.series(cssTaskAdmin, webpack_bundle)),
    gulp.parallel(gulp.series(watch, browsersyncManagement))
);
