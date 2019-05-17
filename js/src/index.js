import React from 'react'
import { render } from 'react-dom';
import FluidCommentWidget from './FluidCommentWidget';

export FluidComment from './FluidComment';
export FluidCommentForm from './FluidCommentForm';
export FluidCommentWidget from './FluidCommentWidget';
export FluidCommentWrapper from './FluidCommentWrapper';
export InlineLoginForm from './InlineLoginForm';
export { getResponseDocument, getDeepProp, getUrl } from './functions';

document.addEventListener("DOMContentLoaded", function() {
  const domContainer = document.querySelector('#fluid-comment-root');
  if (domContainer) {
    const commentedResourceUrl = domContainer.getAttribute('data-fluid-comment-commented-resource-url');
    const commentType = domContainer.getAttribute('data-jsonapi-comment-type');
    render(<FluidCommentWidget commentType={commentType} commentedResourceUrl={commentedResourceUrl} />, domContainer);
  }
});
