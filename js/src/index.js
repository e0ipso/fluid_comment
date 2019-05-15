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
    const hostType = domContainer.getAttribute('data-jsonapi-comment-target-type');
    const hostId = domContainer.getAttribute('data-jsonapi-comment-target-id');
    const commentType = domContainer.getAttribute('data-jsonapi-comment-type');
    render(<FluidCommentWidget commentType={commentType} hostType={hostType} hostId={hostId} />, domContainer);
  }
});
