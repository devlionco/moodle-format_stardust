define(['jquery'], function($) {
'use strict';

const MAINCLASS = `format-stardust`;
const mainBlock = document.querySelector(`.format-stardust .card`);

const toggleSection = (target) => {
  target.parentNode.classList.toggle(`section-opened`);
  $(target).parent().nextAll('.section.img-text').slideToggle();
  $(target).parent().nextAll('.flexsections').slideToggle();
}

const toggleAllSection = (target) => {
  // target.classList.toggle(`section_opened`);
  if (target.classList.contains(`section_opened`)) {
    $('.flexsections-level-0 .section_wrap').removeClass(`section-opened`);
    $(target).removeClass(`section_opened`);
    $('.flexsections-level-0 .section.img-text').slideUp(1000);
    $('.flexsections-level-0 .flexsections-level-2').slideUp(1000);
    $('.flexsections-level-0 .flexsections-level-3').slideUp(1000);
  }else {
    $('.flexsections-level-0 .section_wrap').addClass(`section-opened`);
    $(target).addClass(`section_opened`);
    $('.flexsections-level-0 .section.img-text').slideDown(1000);
    $('.flexsections-level-0 .flexsections-level-2').slideDown(1000);
    $('.flexsections-level-0 .flexsections-level-3').slideDown(1000);
  }

}

// set section numbers
const sectionsnumber = document.querySelector(`#page-course-view-stardust.format-stardust.editing`);
const sections = mainBlock.querySelectorAll(`.section.main`);
const sendAjex = () => {

  let sectionNumbers = [];
  let section = [];
  sections.forEach((item)=>{
    let fakenumber = item.querySelector('.sectionnumber').innerHTML ? item.querySelector('.sectionnumber').innerHTML : 0;
    let sectionid = item.id.replace(/\D+/, '');
    section = [
      sectionid,
      fakenumber
    ];
    if (sectionid && fakenumber) sectionNumbers.push(section);

  });
  sectionNumbers = JSON.stringify(sectionNumbers);

  $.ajax({
      type: "POST",
      async: true,
      url: M.cfg.wwwroot + '/theme/stardust/mypublic-ajax.php',
      data: sectionNumbers,
      success: function(data) {
      },
      error: function(requestObject, error, errorThrown) {
      }
  });
}

    return {
        init: function() {

          if (!mainBlock) return;

          mainBlock.addEventListener('click', function(e){
            let target = e.target;

            while (target != mainBlock) {
              if (target.dataset.handler === `toggleSection`) {
                toggleSection(target);
                return
              }

              if (target.dataset.handler === `openall`) {
                toggleAllSection(target);
                return
              }

              target = target.parentNode;
            }
          });

          if(window.location.href.indexOf('&sectionid') > -1) {
             $('.sectiontoggle').trigger( "click" );
          }

          if (sectionsnumber) {
            sectionsnumber.addEventListener('click', function(e){
              let target = e.target;
              while (!target.classList.contains(MAINCLASS)) {

                if (target.classList.contains(`movehere`)) {
                  sendAjex();
                  return
                }

              target = target.parentNode;
              }
            });
          }

        }
    };
});
