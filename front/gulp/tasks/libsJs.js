import terser from 'gulp-terser' //сжимает js файлы

export const libsJs = () => {
  return app.gulp
    .src(app.path.src.wowJs)
    .pipe(app.plugins.concat('libs.min.js'))
    .pipe(app.plugins.if(app.isBuild, terser()))
    .pipe(app.gulp.dest(app.path.build.js))
    .pipe(app.plugins.browsersync.stream())
}
