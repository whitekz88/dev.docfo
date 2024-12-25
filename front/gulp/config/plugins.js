// *** Общие плагины ***
import replace from 'gulp-replace' // Поиск и замена
import plumber from 'gulp-plumber' // Обработка ошибок
import notify from 'gulp-notify' // Сообщения (подсказки)
import browsersync from 'browser-sync' // Локальный сервер
import newer from 'gulp-newer' // Проверяет обновления изображений, изменяет только новые
import ifPlugin from 'gulp-if' // Условное витвление
import concat from 'gulp-concat' // конкатенирует и переименовывает файлы

// Экспортируем обьект
export const plugins = {
  replace: replace,
  plumber: plumber,
  notify: notify,
  browsersync: browsersync,
  newer: newer,
  if: ifPlugin,
  concat: concat,
}
