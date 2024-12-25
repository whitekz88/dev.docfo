// Получаем имя папки проекта
import * as nodePath from 'path'
const rootFolder = nodePath.basename(nodePath.resolve())

const buildFolder = `./dist` // Также можно использовать rootFolder
const srcFolder = `./src`

// обьект с путями к файлами
export const path = {
  build: {
    // к финальным файлам
    js: `${buildFolder}/js/`,
    css: `${buildFolder}/css/`,
    html: `${buildFolder}/`,
    images: `${buildFolder}/images/`,
    fonts: `${buildFolder}/fonts/`,
    files: `${buildFolder}/files/`,
    // json: project_folder + '/json/',
  },
  src: {
    //  к исходникам
    js: `${srcFolder}/js/app.js`,
    images: `${srcFolder}/images/**/*.{jpg,JPG,jpeg,png,gif,webp}`,
    svg: `${srcFolder}/images/**/*.svg`,
    scss: `${srcFolder}/scss/style.scss`,
    html: `${srcFolder}/*.html`, //.pug
    files: `${srcFolder}/files/**/*.*`,
    svgicons: `${srcFolder}/svgicons/*.svg`,
    wowJs: 'node_modules/wowjs/dist/wow.js',
    wowCss: 'node_modules/animate.css/animate.css',
    // slickCss: 'node_modules/slick-carousel/slick/slick.css',
    // slickJs: 'node_modules/slick-carousel/slick/slick.js',
    // swiperCss: 'node_modules/swiper/swiper.min.css',
    // swiperJs: 'node_modules/swiper/swiper-bundle.js',
  },
  watch: {
    //  пути к файлам которые нужно постоянно слушать
    js: `${srcFolder}/js/**/*.js`,
    scss: `${srcFolder}/scss/**/*.scss`,
    html: `${srcFolder}/**/*.html`, //.pug
    images: `${srcFolder}/images/**/*.{jpg,jpeg,png,svg,gif,ico,webp}`,
    files: `${srcFolder}/files/**/*.*`,
    // json: source_folder + '/json/*.*',
  },
  clean: buildFolder, // путь по которому делаеться удаление после каждого запуска gulp
  buildFolder: buildFolder,
  srcFolder: srcFolder,
  rootFolder: rootFolder,
  ftp: ``, // указать имя папки (если нужно) куда выгрузяться файлы на FTP
}
