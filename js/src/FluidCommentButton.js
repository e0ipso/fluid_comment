import React, { useContext } from 'react';

import FluidCommentContext from './FluidCommentContext';

const FluidCommentButton = ({ text, type="button", handler }) => {
  const { isRefreshing } = useContext(FluidCommentContext);
  const handleClick = (event, handler) => {

    if (isRefreshing) {
      return;
    }

    if (typeof handler === 'function') {
      handler();
    }
  };

  return (
    <button
      className={`button ${isRefreshing && 'is-disabled'}`}
      type={type}
      disabled={isRefreshing}
      onClick={(e) => handleClick(e, handler)}
    >
      {text}
    </button>
  );
}

export default FluidCommentButton;
