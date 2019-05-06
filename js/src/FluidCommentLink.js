'use strict';

import React from 'react';

const FluidCommentLink = ({ link, handleClick }) => (
  <a href="#" onClick={handleClick}>{ link.title }</a>
);

export default FluidCommentLink;
