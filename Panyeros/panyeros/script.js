/* -------- burger menu on mobile -------- */
const burger = document.querySelector('.burger');
const nav    = document.querySelector('nav ul');
burger.addEventListener('click', ()=> {
  nav.classList.toggle('open');
});

/* -------- scroll‑reveal -------- */
const reveals = document.querySelectorAll('.reveal');
const onScroll = () =>{
  const trigger = window.innerHeight * 0.85;
  reveals.forEach(el=>{
    const top = el.getBoundingClientRect().top;
    if(top < trigger) el.classList.add('active');
  });
};
window.addEventListener('scroll',onScroll);
onScroll();          // reveal elements already in view
var elem = document.querySelector('.carousel');
var flkty = new Flickity(elem, {
  wrapAround: true,
  autoPlay: 5000,
  cellAlign: 'left',
  contain: true,
  prevNextButtons: true,
  pageDots: true
});
