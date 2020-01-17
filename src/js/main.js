import UIkit from 'uikit';
import Icons from 'uikit/dist/js/uikit-icons';

// loads the Icon plugin
UIkit.use(Icons);

$(document).ready(function() {
    // Load news on the homepage
    if ($('#homepage-news-container').length) {
        let output = "";
        $.get('https://qcna.omerlocdn.com/production/qcna/site/recent-articles-list.json ', function(data) {
            $.each(data['contents'], function(i, item) {
                let article =
                `<div>
                    <article class="uk-card uk-card-default uk-card-hover uk-card-small uk-background-muted">
                        <div class="uk-card-badge uk-label">${item.channel.en}</div>
                        <div class="uk-card-media-top">
                            <a href="${item.canonicalUrlOverride}" target="_blank">
                                <img src="${item.visual.url}" alt="" width="270" height="180" class="image-fit">
                            </a>
                        </div>
                        <div class="uk-card-body">
                            <p class="uk-text-meta uk-margin-remove">
                                <span uk-icon="icon: calendar"></span> <time datetime="${item.publicationDate}">${item.publicationDate.split('T')[0]}</time>
                            </p>
                            <h5 class="uk-card-title">
                                <a class="uk-link-heading" href="${item.canonicalUrlOverride}" target="_blank">${item.title.en}</a>
                            </h5>
                            <p>${item.lead.en}</p>
                        </div>
                        <div class="uk-card-footer">
                            <a href="${item.canonicalUrlOverride}" class="uk-button uk-button-text" target="_blank">Read more</a>
                        </div>
                    </article>
                </div>`
                output += article;
                return i < 3;
            });
            $('#homepage-news-container').append(output);
        });
    }
    // Load news in the footer
    if ($('#footer-news-container').length) {
        let output = "";
        $.get('https://qcna.omerlocdn.com/production/qcna/popular-news.json', function(data) {
            $.each(data['contents'], function(i, item) {
                let article =
                `<article>
                    <h5><a class="uk-link-heading" href="${item.canonicalUrlOverride}" target="_blank">${item.title.en}</a></h5>
                    <p class="uk-text-meta uk-margin-remove">
                        <span uk-icon="icon: calendar"></span> <time datetime="${item.publicationDate}">${item.publicationDate.split('T')[0]}</time>
                    </p>
                </article>`;
                output += article;
                return i < 1;
            });
            $('#footer-news-container').append(output);
        });
    }
});