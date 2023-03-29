"use strict";
// Window Load
$(window).on("load",function(){

});

// Document Ready
$(document).ready(function(){
    
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
    // $(".main-menu-block").addClass("d-none");
    $(".left-collapse-btn").click(function(){
        $("body").toggleClass("left-open");
        $("body").removeClass("right-open");
        $(".sub-menu-block").toggleClass("sub-tab-hide");
        $(".main-menu-block").toggleClass("d-none");
    });
    
    

    //
    $(".right-collapse-btn").click(function(){
        $("body").toggleClass("right-open");
        $("body").removeClass("left-open");
        $(".sub-menu-block").addClass("sub-tab-hide");
    });

    //
    // $(".sub-drop-panel.open > .sub-drop-body").show();
	
    //
    // $('.acc-header').on('click', function(event) {
    //     $(this).closest(".acc-panel").toggleClass("open").find(".acc-body").slideToggle();
    // });

    //
    // $('.activity-header').on('click', function(event) {
    //     $(this).closest(".activity-panel").toggleClass("open").find(".activity-body").slideToggle();
    // });

    // $('div').removeClass('white-box').addClass('card');
    // $('.white-box').addClass('card');
    
    // $('form').addClass('row'); 
    $('table').addClass('table-hover'); 
    $('.btn-info.btn-outline').removeClass('btn-outline').addClass('btn-outline-success');
    $('.ti-pencil-alt').removeClass('ti-pencil-alt').addClass('mdi mdi-lead-pencil');
    $('.ti-trash').removeClass('ti-trash').addClass('mdi mdi-close');
    // $('/form').prepend($("</div>"));    
    // $( "<div class='table-responsive'>" ).insertBefore( "table" ); 
    $('select').addClass('cust-select');
    $('.dataTables_length select').addClass('cust-select');
    
    $('#page-wrapper').addClass('content-main flex-fill');
});


$('.submenu-sidebar').on('click', function(event) {
    $('.tab-content.sub-menu-block').toggleClass('active');
});

$('.right-sub-sidebar').on('click', function(event) {
    $('.right-sidebar').toggleClass('active');
});

$(function () {
  $('[data-toggle="tooltip"]').tooltip()
})

// Help Guide
$('.help-body').hide(100);
$('.guide-title').on('click', function(event) {
    $('.help-guide').toggleClass('active', 100);
    $('.help-body').slideToggle(100);
});

// $('.collapse').collapse()

// $('.tab-content.active #menu-1').addClass('active');


// $('.panel-click').on('click', function(event) {
//     event.preventDefault();
//     event.stopPropagation();
//     // $(this).parents('.tab-pane').find('.sub-drop-panel').removeClass("open");
//     // $(this).parents('.tab-pane').find('.sub-drop-panel').find('.sub-drop-body').slideUp(500);
//     $(".sub-drop-panel.open > .sub-drop-body").show();
//     // Just Remove .stop("true", "true")
//     $(this).closest('.sub-drop-panel').toggleClass('open').children(".sub-drop-body").slideToggle(100);
//     $(this).closest('.sub-drop-panel').siblings().find(".sub-drop-body").slideUp(100);
//     $(this).closest('.sub-drop-panel').siblings().removeClass("open");
// });

// $('.panel-click').on('click', function(event) {
//     $(this).parents('.tab-content').removeClass('active');
// });	