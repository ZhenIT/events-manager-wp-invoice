jQuery(document).ready( function($){
    //Invoice Links
    $(document).delegate('.em-bookings-invoice', 'click', function(){
        var el = $(this);
        var url = em_ajaxify( el.attr('href'));
        var td = el.parents('td').first();
        td.html(EM.txt_loading);
        td.load( url );
        return false;
    });
});