import clean_css from 'gulp-clean-css' // чистит и сжимает css
export const libsCss = () => {
  return app.gulp
    .src(app.path.src.wowCss)
    .pipe(app.plugins.concat('libs.min.css'))
    .pipe(app.plugins.if(app.isBuild, clean_css()))
    .pipe(app.gulp.dest(app.path.build.css))
    .pipe(app.plugins.browsersync.stream())
}
