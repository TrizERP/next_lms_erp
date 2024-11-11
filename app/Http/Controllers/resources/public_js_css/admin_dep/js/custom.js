"use strict";
// Window Load
$(window).on("load",function(){
    $('.table.dataTable').parent('[class*="col-"]').addClass('table-responsive');
    $('input[type="text"]').addClass('form-control');
    $('.table-responsive').removeClass('col-md-6');
    $('.table').addClass('table-bordered');
});

// Document Ready
$(document).ready(function(){
    //
    $(".nav-link").click(function(){
        var me = $(this);
        var panel = $('#' + this.hash.substr(1).toLowerCase());
        // alert(me);
        // alert(panel);
        if(me.hasClass('active'))
        {
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

    $('.acc-header').on('click', function(event) {
        $(this).closest(".acc-panel").toggleClass("open").find(".acc-body").slideToggle();
    });

    //
    $('.activity-header').on('click', function (event) {
        $(this).closest(".activity-panel").toggleClass("open").find(".activity-body").slideToggle();
    });

    // $('div').removeClass('white-box').addClass('card');
    // $('.white-box').addClass('card');

    // $('form').addClass('row');
    // $('table').addClass('table-hover');
    $('.btn-info.btn-outline').removeClass('btn-outline').addClass('btn-outline-success');
    $('.ti-pencil-alt').removeClass('ti-pencil-alt').addClass('mdi mdi-lead-pencil');
    $('.ti-trash').removeClass('ti-trash').addClass('mdi mdi-close');
    // $('/form').prepend($("</div>"));
    // $( "<div class='table-responsive'>" ).insertBefore( "table" );
    $('select').removeClass('cust-select');
    $('select').removeClass('selectpicker');

    // $('div').removeClass('bootstrap-select');
    $('.form-control').removeClass('bootstrap-select');
    // $('#menu-1').addClass('active');

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

// $( ".col-md-3" ).parent("div").prepend( "<div class='row'>" );
// $( ".col-md-3" ).parent("div").prepend( "</div>" );

// var $target = $('.col-md-3');
// $('<div class="row>').appendTo($target);
// $('</div>').prependTo($target);

// For Row and Col
// var $target = '[class*="col-"]';
// var $parentTarget = 'div,form,p';
$($target).parent($parentTarget).css("display", "flex");
$($target).parent($parentTarget).css("margin-right", "-15px");
$($target).parent($parentTarget).css("margin-left", "-15px");
// $($target).parent($parentTarget).css("width", "100%");
$($target).parent($parentTarget).css("flex-wrap", "wrap");
// $($target).parent($parentTarget).css("padding", "0");
// $($target).parent($parentTarget).css("align-items", "center");
$($target).parent($parentTarget).removeClass('p-0');
$($target).parent($parentTarget).parents('.row').addClass('card');
// $($target).parent($parentTarget).append("<div class='card'></div>");
// $($target).parent($parentTarget).parents('.card').removeClass('[class*="p-"]');
$($target).parent($parentTarget).parents('.row').removeClass('row');
$($target).parent($parentTarget).removeClass('form-group');
// $($target).parent($parentTarget).parents('.row').addClass('card');
$('div,form,p').removeClass('row');

$('input[type="submit"]').addClass('btn-primary')

$('select').addClass('form-control');
$('select').parent('.form-control').removeClass('form-control');

$('.card').children('card').removeClass();
if($('.card').children('card')){
    $(this).children('card').removeClass(400);
}

$('.dropdown.bootstrap-select').on("load",function(){
    $('.dropdown.bootstrap-select').parent('.form-group').children('h4').replaceWith($('<label>' + this.innerHTML + '</label>'))
});

$('.dropdown.bootstrap-select').parent('.form-group').children('h4.box-title').replaceWith($("label.box-title"));
if($('.dropdown.bootstrap-select').parents('.form-group').children( "h4" )){
    $('.bootstrap-select').parents('.col-md-3').children("h4").replaceWith('label', true);
}

// Help Guide
$('.help-body').hide(100);
$('.guide-title').on('click', function(event) {
    $('.help-guide').toggleClass('active', 100);
    $('.help-body').slideToggle(100);
});


$('.mySlides').click(function(){
    $(this).addClass('active');
});

// $(window).on("load",function(){
//     $('.mySlides').click(function() {
//         $(this).toggleClass('yourclass');
//     });
// });
