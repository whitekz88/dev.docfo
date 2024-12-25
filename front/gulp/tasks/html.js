import fileInclude from 'gulp-file-include' //собирает файлы в один
import webpHtmlNosvg from 'gulp-webp-html-nosvg' //преобразует изображения, кроме svg, в webp
import versionNumber from 'gulp-version-number' // избавляет от кеширования файлов в браузере, добавляет ключи
import pug from 'gulp-pug'

export const html = () => {
  return (
    app.gulp
      .src(app.path.src.html)
      .pipe(
        app.plugins.plumber(
          app.plugins.notify.onError({
            title: 'HTML',
            message: 'Error: <%= error.message %>',
          })
        )
      )
      .pipe(fileInclude())
      // .pipe(pug({
      //   // Сжатие HTML файла
      //   pretty: true,
      //   // Показывать в терминале какой файл обработан
      //   verbose: true
      // }))
      .pipe(app.plugins.replace(/@img\//g, 'images/')) // плагин меняет алиасы на правильный путь
      .pipe(app.plugins.if(app.isBuild, webpHtmlNosvg()))
      .pipe(
        app.plugins.if(
          app.isBuild,
          versionNumber({
            value: '%DT%',
            append: {
              key: '_v',
              cover: 0,
              to: ['css', 'js'],
            },
            output: {
              file: 'gulp/version.json',
            },
          })
        )
      )
      .pipe(app.gulp.dest(app.path.build.html))
      .pipe(app.plugins.browsersync.stream())
  )
}
