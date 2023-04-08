"use strict";
// Window Load
$(window).on("load",function(){

});

// Document Ready
$(document).ready(function(){

    //
    $('.cust-select').selectpicker({
        style: '',
        styleBase: 'form-control',
    });
    
    //
    $(".nav-link").click(function(){
        var me = $(this);
        var panel = $('#' + this.hash.substr(1).toLowerCase());
        if(me.hasClass('active')){
           me.removeClass('active');
          panel.removeClass('active');     
              return false;
        }
    });

    //
    $(".left-collapse-btn").click(function(){
        $("body").toggleClass("left-open");
        $("body").removeClass("right-open");
        $(".sub-menu-block").toggleClass("sub-tab-hide");
    });

    //
    $(".right-collapse-btn").click(function(){
        $("body").toggleClass("right-open");
        $("body").removeClass("left-open");
        $(".sub-menu-block").addClass("sub-tab-hide");
    });

    //
    $(".sub-drop-panel.open > .sub-drop-body").show();
	$('.panel-click').on('click', function(event) {
		event.preventDefault();
		event.stopPropagation();
		$(this).closest('.sub-drop-panel').toggleClass('open').children(".sub-drop-body").stop("true", "true").slideToggle(500);
		$(this).closest('.sub-drop-panel').siblings().find(".sub-drop-body").stop("true", "true").slideUp();
		$(this).closest('.sub-drop-panel').siblings().removeClass("open");
    });

    //
    $('.acc-header').on('click', function(event) {
        $(this).closest(".acc-panel").toggleClass("open").find(".acc-body").slideToggle();
    });

    //
    $('.activity-header').on('click', function(event) {
        $(this).closest(".activity-panel").toggleClass("open").find(".activity-body").slideToggle();
    });
});