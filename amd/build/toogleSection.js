define(['jquery'], function($) {
'use strict';

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
  }else {
    $('.flexsections-level-0 .section_wrap').addClass(`section-opened`);
    $(target).addClass(`section_opened`);
    $('.flexsections-level-0 .section.img-text').slideDown(1000);
    $('.flexsections-level-0 .flexsections-level-2').slideDown(1000);
  }

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

        }
    };
});
