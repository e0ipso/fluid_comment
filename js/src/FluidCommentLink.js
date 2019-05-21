import React, { useContext } from 'react';

import FluidCommentContext from './FluidCommentContext';

const FluidCommentLink = ({ text, handler }) => {
  const { isRefreshing } = useContext(FluidCommentContext);
  const handleClick = (event, handler) => {

    event.preventDefault();

    if (isRefreshing) {
      return;
    }

    handler();
  };

  return (
    <a href="#" onClick={(e) => handleClick(e, handler)}>{text}</a>
  );
};

export default FluidCommentLink;
