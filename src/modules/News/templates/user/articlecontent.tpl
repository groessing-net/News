<script type="text/javascript">
    // <![CDATA[
    var bytesused = "{{gt text='#{chars} characters out of 65,536'}}";
    // ]]>
</script>

<span class="news_category">
{foreach name='categorylinks' from=$preformat.categories item='categorylink'}
{$categorylink}
{if $smarty.foreach.categorylinks.last neq true}<span class="text_separator"> | </span>{/if}
{/foreach}
</span>
<h3 class="news_title">{$info.catandtitle}</h3>

{nocache}
<div id="news_editlinks">{articleadminlinks sid=$info.sid}</div>
{if $enableajaxedit}
<div id="news_editlinks_ajax" class="hidelink">{articleadminlinks sid=$info.sid page=$page type='ajax'}</div>
{/if}
{/nocache}

<p class="news_meta z-sub">{gt text='Contributed'} {gt text='by %1$s on %2$s' tag1=$info.contributor tag2=$info.from|dateformat:'datetimebrief'}</p>

{if $links.searchtopic neq '' AND $info.topicimage neq ''}
<p id="news_topic" class="news_meta"><a href="{$links.searchtopic}"><img src="{$catimagepath}{$info.topicimage}" alt="{$info.topicname}" title="{$info.topicname}" /></a></p>
{/if}

<div id="news_body" class="news_body">
    {if $picupload_enabled AND $info.pictures gt 0}
    <div class="news_photo news_thumbs" style="float:{$picupload_article_float}">
        <a href="{$picupload_uploaddir}/pic_sid{$info.sid}-0-norm.png" rel="imageviewer[sid{$info.sid}]">{*<span></span>*}<img src="{$picupload_uploaddir}/pic_sid{$info.sid}-0-thumb2.png" alt="{gt text='Picture %s for %s' tag1='0' tag2=$info.title}" /></a>
    </div>
    {/if}
    <div class="news_hometext">
        {$preformat.hometext}
    </div>
    {$preformat.bodytext}

    <p class="news_footer">
        {$preformat.print}
        {if $pdflink}
        <span class="text_separator">|</span>
        <a title="PDF" href="{modurl modname='News' type='user' func='displaypdf' sid=$info.sid}" target="_blank">PDF <img src="modules/News/images/pdf.gif" width="16" height="16" alt="PDF" /></a>
        {/if}
    </p>
    
    {if $picupload_enabled AND $info.pictures gt 1}
    <div class="news_pictures"><div><strong>{gt text='Picture gallery'}</strong></div>
        {section name=counter start=1 loop=$info.pictures step=1}
            <div class="news_photoslide news_thumbsslide">
                <a href="{$picupload_uploaddir}/pic_sid{$info.sid}-{$smarty.section.counter.index}-norm.png" rel="imageviewer[sid{$info.sid}]"><span></span>
                <img src="{$picupload_uploaddir}/pic_sid{$info.sid}-{$smarty.section.counter.index}-thumb.png" alt="{gt text='Picture %s for %s' tag1=$smarty.section.counter.index tag2=$info.title}" /></a>
            </div>
        {/section}
    </div>
    {/if}
</div>

{if $preformat.notes neq ''}
<span id="news_notes" class="news_meta">{$preformat.notes}</span>
{/if}

{* the next code is to display the pager *}
{pager rowcount=$pager.numitems limit=$pager.itemsperpage posvar='page'}