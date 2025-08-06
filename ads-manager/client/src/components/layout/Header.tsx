import React from 'react';

const Header: React.FC = () => {
  return (
    <header className="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
      <div className="px-6 py-4">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Dashboard</h2>
      </div>
    </header>
  );
};

export default Header;