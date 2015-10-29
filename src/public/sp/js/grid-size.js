/**
 * Get width and height of browser
 *
 *  * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/20
 */

var widthMinus = 400;
var heightMinus = 300;

$(window).bind('load resize', getWidth);
$(window).bind('load resize', getHeight);

function getWidth() 
{
    var w = $(window).width();
    w = w - widthMinus;
    
    return w;
}

function getHeight() 
{
    var h = $(window).height();
    h = h - heightMinus;
    return h;
}
    

	