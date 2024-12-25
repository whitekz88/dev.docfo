// Импорт функционала =================================================================
// import { isMobileOrTablet } from './functions.js'

window.onload = function () {
  // Делегирование
  document.addEventListener('click', documentActions)

  // Actions (делегирование события click)
  function documentActions(e) {
  // получаю элемент на который кликнул
  const targetElement = e.target

  // mobile menu
  if (targetElement.classList.contains('icon-menu')) {
    document.querySelector('.icon-menu').classList.toggle('menu-open')
    document.querySelector('.menu__body').classList.toggle('menu-open')
    document.querySelector('html').classList.toggle('menu-open')
  }
}
}

