/*
Документация по работе в шаблоне
Документация слайдера https://swiperjs.com/
Снипет(HTML): swiper
*/

// Подключаем слайдер Swiper из node_modules
// При необходимости подключаем дополнительные модули слайдера, указывая их в {} через запятую
// Пример: {Navigation, Autoplay, Pagination, Parallax, Autoplay}
// В 1 слайдере можно выводить или фракцию или пагинацию
import Swiper, { Navigation, Pagination, Parallax, Autoplay } from 'swiper'
/*
Основные модули слайдера:
Navigation, Autoplay, Pagination, EffectFade, Lazy, Manipulation
Подробнее смотри https://swiperjs.com/
/ ***  Стили Swiper
Полный набор стилей из node_modules
 */
// import 'swiper/css';
// import 'swiper/css/navigation';
// import 'swiper/css/pagination';

// Добавление классов слайдерам
// swiper: главному блоку (обычно имеет класс slider который передаем при инициализации Swiper), swiper-wrapper: оболочке, swiper-slide: для слайдеров. Тоесть при создании слайдера должно быть 3 вложености.

function bildSliders() {
  // BildSlider
  let sliders = document.querySelectorAll(
    '[class*="__swiper"]:not(.swiper-wrapper)'
  )

  if (sliders) {
    sliders.forEach((slider) => {
      slider.parentElement.classList.add('swiper')
      slider.classList.add('swiper-wrapper')

      let slider_items = slider.children

      if (slider_items) {
        for (let slide of slider_items) {
          slide.classList.add('swiper-slide')
        }
      }
    })
  }
}

// Инициализация слайдеров
function initSliders() {
  // Добавление классов слайдера
  // при необходимости отключить
  bildSliders()

  // Перечень слайдеров
  if (document.querySelector('.main-block__slider')) {
    new Swiper('.main-block__slider', {
      // Подключаем модули слайдера
      // для конкретного случая
      modules: [Navigation, Pagination, Parallax, Autoplay],
      /*
        effect: 'fade',
       */
      autoplay: {
        delay: 3000,
        disableOnInteraction: false,
      },

      observer: true,
      observeParents: true,
      slidesPerView: 1,
      spaceBetween: 50,
      // autoHeight: true,
      speed: 800,
      // watchOverflow: true,
      //  touchRatio: 0,
      //  simulateTouch: false,
      loop: true,
      // loopAdditionalSlides: 5,
      // preloadImages: false,
      parallax: true,
      // Lazy: true,
      // Dots
      pagination: {
        el: '.controll-main-block__dotts',
        clickable: true,
      },
      // Arrows
      // navigation: {
      //   nextEl: '.slider-main .slider-arrow_next',
      //   prevEl: '.slider-main .slider-arrow_prev',
      // },
      //  breakpoints: {
      //   // when window width is >= 320px
      //   320: {
      //     slidesPerView: 1.1,
      //     spaceBetween: 15,
      //   },
      //   // when window width is >= 768px
      //   768: {
      //     slidesPerView: 2,
      //     spaceBetween: 20,
      //   },
      //   // when window width is >= 992px
      //   768: {
      //     slidesPerView: 2,
      //     spaceBetween: 32,
      //   },
      // },
      // on: {
        // Кастомная фракция
      //   init: function (swiper) {
      //     const allSlides = document.querySelector('.fraction-controll__all')
      //     const allSlidesItems = document.querySelectorAll(
      //       '.slide-main-block:not(.swiper-slide-duplicate)'
      //     )
      //     allSlides.innerHTML =
      //       allSlidesItems.length < 10
      //         ? `0${allSlidesItems.length}`
      //         : allSlidesItems.length
      //   },
      //   slideChange: function (swiper) {
      //     const currentSlide = document.querySelector(
      //       '.fraction-controll__current'
      //     )
      //     currentSlide.innerHTML =
      //       swiper.realIndex + 1 < 10
      //         ? `0${swiper.realIndex + 1}`
      //         : swiper.realIndex + 1
      //   },
      // },
    })
  }
}

// Скролл на базе слайдера(по классу swiper_scroll для оболочки слайдера)

function initSlidersScroll() {
  // Добавление классов слайдера
  // при необходимости отключить
  bildSliders()

  let sliderScrollItems = document.querySelectorAll('._swiper_scroll')
  if (sliderScrollItems.length > 0) {
    for (let index = 0; index < sliderScrollItems.length; index++) {
      const sliderScrollItem = sliderScrollItems[index]
      const sliderScrollBar =
        sliderScrollItem.querySelector('.swiper-scrollbar')
      const sliderScroll = new Swiper(sliderScrollItem, {
        observer: true,
        observeParent: true,
        direction: 'vertical',
        slidesPerView: 'auto',
        freeMode: true,
        scrollbar: {
          el: sliderScrollBar,
          draggable: true,
          snapOnRelease: false,
        },
        mousewheel: {
          releaseOnEdges: true,
        },
      })
      sliderScroll.scrollbar.updateSize()
    }
  }
}

window.addEventListener('load', function (e) {
  // Запуск инициализации слайдеров
  initSliders()
  // Запуск инициализации скролла на базе слайдера (по классу swiper_scroll)
  initSlidersScroll()
})
