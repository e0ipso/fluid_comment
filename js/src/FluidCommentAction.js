import React from 'react';

import FluidCommentForm from './FluidCommentForm';
import FluidCommentButton from './FluidCommentButton';

const FluidCommentAction = (
    { name, title, subject, body, handleEdit, handleConfirm, handleCancel, formKey }
  ) => (
  <div className="comment__content">
    {name === 'update'
      ? <FluidCommentForm
        formKey
        key={formKey}
        handleSubmit={handleEdit}
        handleCancel={handleCancel}
        defaultValues={{ subject, body }}
      />
      : <ul className="links inline">
        <li>{title}</li>
        <li>
          <FluidCommentButton text={'Confirm'} handler={handleConfirm} />
          <FluidCommentButton text={'Cancel'} handler={handleCancel} />
        </li>
      </ul>
    }
  </div>
);

export default FluidCommentAction;
