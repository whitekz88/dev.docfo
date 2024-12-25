// Основной модуль
import gulp from 'gulp'
// Импорт путей
import { path } from './gulp/config/path.js'
// Импорт общих плагинов
import { plugins } from './gulp/config/plugins.js'
// Передаем значения в глобальную переменную
global.app = {
  isBuild: process.argv.includes('--build'),
  isDev: !process.argv.includes('--build'),
  path: path,
  gulp: gulp,
  plugins: plugins,
}

// Импорт задач
import { copy } from './gulp/tasks/copy.js'
import { reset } from './gulp/tasks/reset.js'
import { html } from './gulp/tasks/html.js'
import { server } from './gulp/tasks/server.js'
import { scss } from './gulp/tasks/scss.js'
import { js } from './gulp/tasks/js.js'
import { images } from './gulp/tasks/images.js'
import { otfToTtf, ttfToWoff, fontsStyle } from './gulp/tasks/fonts.js'
import { svgSprive } from './gulp/tasks/svgSprive.js'
import { zip } from './gulp/tasks/zip.js'
import { ftp } from './gulp/tasks/ftp.js'
import { libsJs } from './gulp/tasks/libsJs.js'
import { libsCss } from './gulp/tasks/libsCss.js'

// Наблюдатель за изменениями в файлах
// поменять задачи на закоментированные если хотим автоматически обновлять изменения на FTP gulp.watch(path.watch.files, gulp.series(copy, ftp))
function watcher() {
  gulp.watch(path.watch.files, copy) //gulp.series(copy, ftp)
  gulp.watch(path.watch.html, html) //gulp.series(html, ftp)
  gulp.watch(path.watch.scss, gulp.parallel(scss, libsCss)) //gulp.series(scss, ftp)
  gulp.watch(path.watch.js, gulp.parallel(js, libsJs)) //gulp.series(js, ftp)
  gulp.watch(path.watch.images, images) //gulp.series(images, ftp)
}

// Для отдельного запуска командой npm run svgSprive
export { svgSprive }

// Последовательная обработка шрифтов
const fonts = gulp.series(otfToTtf, ttfToWoff, fontsStyle)
// Последовательная обработка файлов библиотек
const libs = gulp.parallel(libsJs, libsCss)

//Основные задачи
const mainTasks = gulp.series(
  fonts,
  gulp.parallel(copy, html, scss, js, libs, images)
)

// Построение сценариев выполнения задач
const dev = gulp.series(reset, mainTasks, gulp.parallel(watcher, server))
const build = gulp.series(reset, mainTasks)
const deployZIP = gulp.series(reset, mainTasks, zip)
const deployFTP = gulp.series(reset, mainTasks, ftp)

// Экспорт сценариев для отдельногозапуска командой npm run ...
export { dev }
export { build }
export { deployZIP }
export { deployFTP }

// Выполнение сценария по умолчанию
gulp.task('default', dev)


