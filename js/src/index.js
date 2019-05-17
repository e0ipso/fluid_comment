import React from 'react'
import { render } from 'react-dom';
import FluidCommentWrapper from './FluidCommentWrapper';
import { getResponseDocument, getDeepProp } from './functions';

document.addEventListener("DOMContentLoaded", function() {
    const domContainer = document.querySelector('#fluid-comment-root');
    if (domContainer) {
        const commentedResourceUrl = domContainer.getAttribute('data-fluid-comment-commented-resource-url');
        const commentType = domContainer.getAttribute('data-jsonapi-comment-type');
        const filterDefaultFormat = domContainer.getAttribute('data-fluid-comment-filter-default-format');
        getResponseDocument(commentedResourceUrl).then(doc => {
            const commentedResource = getDeepProp(doc, 'data');
            render((commentedResource &&
                <FluidCommentWrapper
                    commentType={commentType}
                    currentNode={commentedResource}
                    filterDefaultFormat={filterDefaultFormat}
                />
            ), domContainer);
        });
    }
});
